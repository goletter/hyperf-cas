<?php

declare(strict_types=1);

namespace Goletter\Cas;

use Goletter\Cas\Contract\UserResolverInterface;

class DefaultUserResolver implements UserResolverInterface
{
    public function resolve(CasUser $user): int|string|null
    {
        return $user->user;
    }
}
