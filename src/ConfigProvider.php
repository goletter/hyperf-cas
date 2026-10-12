<?php

declare(strict_types=1);

namespace Goletter\Cas;

use Goletter\Cas\Contract\TokenManagerInterface;
use Goletter\Cas\Contract\UserResolverInterface;
use Goletter\Cas\Token\RedisTokenManager;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                TokenManagerInterface::class => RedisTokenManager::class,
                UserResolverInterface::class => DefaultUserResolver::class,
            ],
            'annotations'  => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'publish'      => [
                [
                    'id'          => 'config',
                    'description' => 'The config for cas component.',
                    'source'      => __DIR__ . '/../publish/cas.php',
                    'destination' => BASE_PATH . '/config/autoload/cas.php',
                ],
            ],
        ];
    }
}
