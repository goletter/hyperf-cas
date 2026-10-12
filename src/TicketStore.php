<?php

declare(strict_types=1);

namespace Goletter\Cas;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Redis\RedisFactory;
use Hyperf\Redis\RedisProxy;

class TicketStore
{
    private const PULL_SCRIPT = <<<'LUA'
        local v = redis.call('GET', KEYS[1])
        if v then redis.call('DEL', KEYS[1]) end
        return v
        LUA;

    public function __construct(private ConfigInterface $config, private RedisFactory $redisFactory)
    {
    }

    public function bindTicket(string $ticket, string $tokenId): void
    {
        $this->redis()->setex($this->key("st:{$ticket}"), (int) $this->config->get('cas.token.ttl', 7200), $tokenId);
    }

    public function pullTicket(string $ticket): ?string
    {
        return $this->pull($this->key("st:{$ticket}"));
    }

    public function createCode(array $token): string
    {
        $code = bin2hex(random_bytes(16));
        $this->redis()->setex($this->key("code:{$code}"), (int) $this->config->get('cas.token.code_ttl', 60), json_encode($token));

        return $code;
    }

    public function pullCode(string $code): ?array
    {
        $data = $code === '' ? null : $this->pull($this->key("code:{$code}"));

        return $data ? json_decode($data, true) : null;
    }

    private function pull(string $key): ?string
    {
        return $this->redis()->eval(self::PULL_SCRIPT, [$key], 1) ?: null;
    }

    private function redis(): RedisProxy
    {
        return $this->redisFactory->get($this->config->get('cas.token.redis_pool', 'default'));
    }

    private function key(string $suffix): string
    {
        return $this->config->get('cas.token.prefix', 'cas:') . $suffix;
    }
}
