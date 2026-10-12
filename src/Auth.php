<?php

declare(strict_types=1);

namespace Goletter\Cas;

use Hyperf\Context\Context;

class Auth
{
    public const CONTEXT_KEY = 'cas.claims';

    public static function id(): int|string|null
    {
        return self::claims()['sub'] ?? null;
    }

    public static function claims(): array
    {
        return Context::get(self::CONTEXT_KEY, []);
    }
}
