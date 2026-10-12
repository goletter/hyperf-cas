<?php

declare(strict_types=1);

namespace Goletter\Cas\Controller;

use Goletter\Cas\CasClient;
use Goletter\Cas\Contract\TokenManagerInterface;
use Goletter\Cas\Contract\UserResolverInterface;
use Goletter\Cas\Event\CasAuthenticated;
use Goletter\Cas\Event\CasLoggedOut;
use Goletter\Cas\Exception\CasException;
use Goletter\Cas\TicketStore;
use Hyperf\Contract\ConfigInterface;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

class CasController
{
    public function __construct(
        private ConfigInterface $config,
        private CasClient $client,
        private TicketStore $store,
        private TokenManagerInterface $tokens,
        private UserResolverInterface $resolver,
        private EventDispatcherInterface $dispatcher,
        private RequestInterface $request,
        private ResponseInterface $response,
    ) {
    }

    public function login(): PsrResponseInterface
    {
        $redirect = $this->safeRedirect($this->request->query('redirect'));

        return $this->response->redirect($this->client->loginUrl($this->callbackUrl($redirect)));
    }

    public function callback(): PsrResponseInterface
    {
        if ($this->request->isMethod('POST') && $logoutRequest = $this->request->post('logoutRequest')) {
            return $this->singleLogout((string) $logoutRequest);
        }

        $redirect = $this->safeRedirect($this->request->query('redirect'));
        $service = $this->callbackUrl($redirect);
        $ticket = (string) $this->request->query('ticket', '');
        if ($ticket === '') {
            return $this->response->redirect($this->client->loginUrl($service));
        }

        try {
            $casUser = $this->client->validate($ticket, $service);
        } catch (CasException $e) {
            return $this->response->redirect($this->withQuery($redirect, ['cas_error' => $e->casCode]));
        }

        $subject = $this->resolver->resolve($casUser);
        if ($subject === null) {
            return $this->response->redirect($this->withQuery($redirect, ['cas_error' => 'USER_FORBIDDEN']));
        }

        $token = $this->tokens->issue($subject, ['cas_user' => $casUser->user]);
        $this->store->bindTicket($ticket, $token['id']);
        $this->dispatcher->dispatch(new CasAuthenticated($casUser, $subject, $ticket, $token['id']));

        unset($token['id']);

        return $this->response->redirect($this->withQuery($redirect, ['code' => $this->store->createCode($token)]));
    }

    public function token(): PsrResponseInterface
    {
        $token = $this->store->pullCode((string) $this->request->input('code', ''));

        return $token
            ? $this->response->json($token)
            : $this->response->json(['code' => 400, 'message' => 'Invalid or expired code'])->withStatus(400);
    }

    public function logout(): PsrResponseInterface
    {
        $header = $this->request->getHeaderLine('Authorization');
        $claims = str_starts_with($header, 'Bearer ') ? $this->tokens->verify(substr($header, 7)) : null;
        $claims && $this->tokens->revoke($claims['id']);

        return $this->response->json([
            'logout_url' => $this->client->logoutUrl($this->config->get('cas.frontend.default_redirect')),
        ]);
    }

    private function singleLogout(string $logoutRequest): PsrResponseInterface
    {
        $allowed = $this->config->get('cas.single_logout.allowed_ips', []);
        $ip = $this->request->getServerParams()['remote_addr'] ?? '';
        if (! $this->config->get('cas.single_logout.enabled', true) || ($allowed && ! in_array($ip, $allowed, true))) {
            return $this->response->raw('forbidden')->withStatus(403);
        }

        $ticket = $this->client->parseLogoutRequest($logoutRequest);
        if ($ticket && $tokenId = $this->store->pullTicket($ticket)) {
            $this->tokens->revoke($tokenId);
            $this->dispatcher->dispatch(new CasLoggedOut($ticket, $tokenId));
        }

        return $this->response->raw('ok');
    }

    private function callbackUrl(string $redirect): string
    {
        return rtrim($this->config->get('cas.service_base_url'), '/')
            . $this->config->get('cas.callback_path', '/cas/callback')
            . '?' . http_build_query(['redirect' => $redirect]);
    }

    private function safeRedirect(mixed $redirect): string
    {
        $host = is_string($redirect) ? parse_url($redirect, PHP_URL_HOST) : null;

        return $host && in_array($host, $this->config->get('cas.frontend.allowed_hosts', []), true)
            ? $redirect
            : $this->config->get('cas.frontend.default_redirect');
    }

    private function withQuery(string $url, array $params): string
    {
        [$url, $fragment] = array_pad(explode('#', $url, 2), 2, null);
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);

        return $fragment === null ? $url : "{$url}#{$fragment}";
    }
}
