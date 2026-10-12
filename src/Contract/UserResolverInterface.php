<?php

declare(strict_types=1);

namespace Goletter\Cas\Contract;

use Goletter\Cas\CasUser;

interface UserResolverInterface
{
    /**
     * 返回本地用户 ID（可在此自动建档）；返回 null 表示拒绝登录.
     */
    public function resolve(CasUser $user): int|string|null;
}
