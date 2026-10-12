<?php

declare(strict_types=1);

namespace Goletter\Cas\Event;

use Goletter\Cas\CasUser;

class CasAuthenticated
{
    public function __construct(
        public readonly CasUser $user,
        public readonly int|string $subject,
        public readonly string $ticket,
        public readonly string $tokenId,
    ) {
    }
}
