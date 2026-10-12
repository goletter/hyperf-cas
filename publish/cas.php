<?php

declare(strict_types=1);

use function Hyperf\Support\env;

return [
    'server' => [
        'base_url'   => env('CAS_SERVER_URL', 'https://cas.example.com/cas'),
        // 2.0 | 3.0（3.0 才返回 attributes）
        'version'    => env('CAS_VERSION', '3.0'),
        'timeout'    => 5,
        'verify_ssl' => true,
    ],
    // 后端 API 对外访问地址（CAS 回调到这里）
    'service_base_url' => env('CAS_SERVICE_BASE_URL', 'https://api.example.com'),
    // 需与路由中 callback 的路径一致
    'callback_path'    => '/cas/callback',
    'frontend' => [
        'default_redirect' => env('CAS_FRONTEND_URL', 'https://admin.example.com/'),
        // redirect 参数允许的前端域名，防止开放重定向
        'allowed_hosts'    => ['admin.example.com'],
    ],
    'token' => [
        'redis_pool' => 'default',
        'prefix'     => 'cas:',
        'ttl'        => 7200,
        'code_ttl'   => 60,
    ],
    'single_logout' => [
        'enabled'     => true,
        // CAS 服务器 IP，留空不限制
        'allowed_ips' => [],
    ],
];
