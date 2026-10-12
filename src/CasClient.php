<?php

declare(strict_types=1);

namespace Goletter\Cas;

use Goletter\Cas\Exception\CasException;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Guzzle\ClientFactory;

class CasClient
{
    private const NS = 'http://www.yale.edu/tp/cas';

    public function __construct(private ConfigInterface $config, private ClientFactory $clientFactory)
    {
    }

    public function loginUrl(string $service, bool $renew = false): string
    {
        $query = ['service' => $service] + ($renew ? ['renew' => 'true'] : []);

        return $this->baseUrl() . '/login?' . http_build_query($query);
    }

    public function logoutUrl(?string $service = null): string
    {
        return $this->baseUrl() . '/logout' . ($service ? '?' . http_build_query(['service' => $service]) : '');
    }

    public function validate(string $ticket, string $service): CasUser
    {
        $path = $this->config->get('cas.server.version') === '2.0' ? '/serviceValidate' : '/p3/serviceValidate';

        $response = $this->clientFactory->create([
            'timeout' => $this->config->get('cas.server.timeout', 5),
            'verify'  => $this->config->get('cas.server.verify_ssl', true),
        ])->get($this->baseUrl() . $path, ['query' => ['ticket' => $ticket, 'service' => $service]]);

        $xml = simplexml_load_string((string) $response->getBody(), options: LIBXML_NONET | LIBXML_NOCDATA);
        if ($xml === false) {
            throw new CasException('INVALID_RESPONSE', 'CAS server returned malformed XML');
        }

        $root = $xml->children(self::NS);
        if (isset($root->authenticationFailure)) {
            $failure = $root->authenticationFailure;
            throw new CasException((string) $failure->attributes()->code, trim((string) $failure));
        }
        if (! isset($root->authenticationSuccess)) {
            throw new CasException('INVALID_RESPONSE', 'Unexpected CAS response');
        }

        $success = $root->authenticationSuccess;
        $attributes = [];
        if (isset($success->attributes)) {
            foreach ($success->attributes->children(self::NS) as $name => $value) {
                $value = (string) $value;
                $attributes[$name] = isset($attributes[$name])
                    ? array_merge((array) $attributes[$name], [$value])
                    : $value;
            }
        }

        return new CasUser(trim((string) $success->user), $attributes);
    }

    public function parseLogoutRequest(string $logoutRequest): ?string
    {
        $xml = simplexml_load_string($logoutRequest, options: LIBXML_NONET);
        if ($xml === false) {
            return null;
        }
        $xml->registerXPathNamespace('samlp', 'urn:oasis:names:tc:SAML:2.0:protocol');
        $nodes = $xml->xpath('//samlp:SessionIndex');

        return $nodes ? trim((string) $nodes[0]) : null;
    }

    private function baseUrl(): string
    {
        return rtrim($this->config->get('cas.server.base_url'), '/');
    }
}
