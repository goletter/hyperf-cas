<?php

declare(strict_types=1);

namespace Goletter\Cas\Contract;

interface TokenManagerInterface
{
    /**
     * @return array{access_token: string, token_type: string, expires_in: int, id: string}
     */
    public function issue(int|string $subject, array $claims = []): array;

    /**
     * @return null|array 包含 sub、id 及签发时的 claims，无效返回 null
     */
    public function verify(string $token): ?array;

    public function revoke(string $id): void;
}
