<?php

declare(strict_types=1);

namespace Goletter\Cas\Exception;

class CasException extends \RuntimeException
{
    public function __construct(public readonly string $casCode, string $message)
    {
        parent::__construct("[{$casCode}] {$message}");
    }
}
