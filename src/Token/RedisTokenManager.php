<?php

declare(strict_types=1);

namespace Goletter\Cas\Token;

use Goletter\Cas\Contract\TokenManagerInterface;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Redis\RedisFactory;
use Hyperf\Redis\RedisProxy;

class RedisTokenManager implements TokenManagerInterface
{
    public function __construct(private ConfigInterface $config, private RedisFactory $redisFactory)
    {
    }

    public function issue(int|string $subject, array $claims = []): array
    {
        $token = bin2hex(random_bytes(32));
        $id = hash('sha256', $token);
        $ttl = (int) $this->config->get('cas.token.ttl', 7200);

        $this->redis()->setex($this->key($id), $ttl, json_encode(
            ['sub' => $subject, 'id' => $id] + $claims,
            JSON_UNESCAPED_UNICODE
        ));

        return ['access_token' => $token, 'token_type' => 'Bearer', 'expires_in' => $ttl, 'id' => $id];
    }

    public function verify(string $token): ?array
    {
        $data = $this->redis()->get($this->key(hash('sha256', $token)));

        return $data ? json_decode($data, true) : null;
    }

    public function revoke(string $id): void
    {
        $this->redis()->del($this->key($id));
    }

    private function redis(): RedisProxy
    {
        return $this->redisFactory->get($this->config->get('cas.token.redis_pool', 'default'));
    }

    private function key(string $id): string
    {
        return $this->config->get('cas.token.prefix', 'cas:') . 'token:' . $id;
    }
}
