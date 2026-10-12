<?php

declare(strict_types=1);

namespace Goletter\Cas\Event;

class CasLoggedOut
{
    public function __construct(public readonly string $ticket, public readonly string $tokenId)
    {
    }
}
