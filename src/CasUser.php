<?php

declare(strict_types=1);

namespace Goletter\Cas;

class CasUser implements \JsonSerializable
{
    public function __construct(public readonly string $user, public readonly array $attributes = [])
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public static function fromArray(array $data): self
    {
        return new self($data['user'], $data['attributes'] ?? []);
    }

    public function jsonSerialize(): array
    {
        return ['user' => $this->user, 'attributes' => $this->attributes];
    }
}
