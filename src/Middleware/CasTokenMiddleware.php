<?php

declare(strict_types=1);

namespace Goletter\Cas\Middleware;

use Goletter\Cas\Auth;
use Goletter\Cas\Contract\TokenManagerInterface;
use Hyperf\Context\Context;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CasTokenMiddleware implements MiddlewareInterface
{
    public function __construct(private TokenManagerInterface $tokens, private HttpResponse $response)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = $request->getHeaderLine('Authorization');
        $claims = str_starts_with($header, 'Bearer ') ? $this->tokens->verify(substr($header, 7)) : null;

        if (! $claims) {
            return $this->response->json(['code' => 401, 'message' => 'Unauthenticated'])->withStatus(401);
        }

        Context::set(Auth::CONTEXT_KEY, $claims);

        return $handler->handle($request);
    }
}
