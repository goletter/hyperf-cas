<h1 align="center">hyperf-cas</h1>

<p align="center">
<a href="https://packagist.org/packages/goletter/hyperf-cas"><img src="https://poser.pugx.org/goletter/hyperf-cas/v/stable" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/goletter/hyperf-cas"><img src="https://poser.pugx.org/goletter/hyperf-cas/downloads" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/goletter/hyperf-cas"><img src="https://poser.pugx.org/goletter/hyperf-cas/v/unstable" alt="Latest Unstable Version"></a>
<a href="https://packagist.org/packages/goletter/hyperf-cas"><img src="https://poser.pugx.org/goletter/hyperf-cas/license" alt="License"></a>
</p>

面向前后端分离架构的 Hyperf CAS 单点登录客户端：浏览器整页跳转完成 CAS 认证，后端签发 Bearer Token，前端通过 `Authorization` 访问业务接口，并支持 CAS 单点登出（SLO）。

## 运行环境

- php >= 8.1
- ext-simplexml
- composer
- hyperf >= 3.1
- redis

## 安装

```bash
composer require goletter/hyperf-cas
```

## 配置

发布配置：

```bash
php bin/hyperf.php vendor:publish goletter/hyperf-cas
```

配置文件 `config/autoload/cas.php`：

```php
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
```

环境变量示例：

```env
CAS_SERVER_URL=https://cas.example.com/cas
CAS_VERSION=3.0
CAS_SERVICE_BASE_URL=https://api.example.com
CAS_FRONTEND_URL=https://admin.example.com/
```

## 登录流程

1. 前端跳转 `GET /cas/login?redirect=前端地址`
2. 后端 302 到 CAS：`{cas}/login?service=/cas/callback?redirect=...`
3. CAS 登录成功后回跳 `GET /cas/callback?ticket=ST-xxx&redirect=...`
4. 后端校验 ticket，签发 token，生成一次性 `code`，302 回前端：`前端地址?code=xxx`
5. 前端 `POST /cas/token` 用 `code` 换取 `access_token`
6. 后续请求携带 `Authorization: Bearer {access_token}`
7. CAS 单点登出时 POST `/cas/callback`（`logoutRequest`），后端按 ST 作废对应 token

> 不在 URL 中直接携带 token，避免泄漏到浏览器历史、Referer 和访问日志。

## 路由

在 `config/routes.php` 中注册：

```php
use Goletter\Cas\Controller\CasController;
use Goletter\Cas\Middleware\CasTokenMiddleware;
use Hyperf\HttpServer\Router\Router;

Router::addGroup('/cas', function () {
    Router::get('/login', [CasController::class, 'login']);
    // callback 需同时支持 GET（登录回调）和 POST（单点登出）
    Router::addRoute(['GET', 'POST'], '/callback', [CasController::class, 'callback']);
    Router::post('/token', [CasController::class, 'token']);
    Router::post('/logout', [CasController::class, 'logout']);
});

Router::addGroup('/api', function () {
    // 业务路由
}, ['middleware' => [CasTokenMiddleware::class]]);
```

## 本地用户映射

默认实现直接使用 CAS 用户名作为 subject。若要映射到本地用户表，实现 `UserResolverInterface` 并绑定：

```php
<?php

declare(strict_types=1);

namespace App\Auth;

use Goletter\Cas\CasUser;
use Goletter\Cas\Contract\UserResolverInterface;

class CasUserResolver implements UserResolverInterface
{
    public function resolve(CasUser $user): int|string|null
    {
        // 查找或创建本地用户，返回用户 ID；返回 null 表示拒绝登录
        return $user->user;
    }
}
```

```php
// config/autoload/dependencies.php
return [
    \Goletter\Cas\Contract\UserResolverInterface::class => \App\Auth\CasUserResolver::class,
];
```

登录成功后可监听 `CasAuthenticated`，登出时可监听 `CasLoggedOut`。

## 业务侧获取当前用户

```php
use Goletter\Cas\Auth;

$userId = Auth::id();
$claims = Auth::claims();
```

## 前端接入示例

```js
const API = 'https://api.example.com';

export const toLogin = () => {
  const url = new URL(location.href);
  url.searchParams.delete('code');
  location.href = `${API}/cas/login?redirect=${encodeURIComponent(url.toString())}`;
};

export async function bootstrapAuth() {
  const params = new URLSearchParams(location.search);
  const code = params.get('code');
  if (code) {
    const { data } = await axios.post(`${API}/cas/token`, { code });
    localStorage.setItem('token', data.access_token);
    params.delete('code');
    history.replaceState(null, '', `${location.pathname}${params.size ? `?${params}` : ''}${location.hash}`);
  }
  if (!localStorage.getItem('token')) toLogin();
}

axios.interceptors.request.use((cfg) => {
  const token = localStorage.getItem('token');
  if (token) cfg.headers.Authorization = `Bearer ${token}`;
  return cfg;
});

axios.interceptors.response.use((r) => r, (err) => {
  if (err.response?.status === 401) {
    localStorage.removeItem('token');
    toLogin();
  }
  return Promise.reject(err);
});

export async function logout() {
  const { data } = await axios.post(`${API}/cas/logout`);
  localStorage.removeItem('token');
  location.href = data.logout_url;
}
```

## 自定义 Token

默认使用 Redis 不透明 Token（`RedisTokenManager`），支持立即作废，便于 SLO。若要换成 JWT，实现 `TokenManagerInterface` 并重新绑定依赖即可。注意：纯无状态 JWT 无法直接支持单点登出，需要额外维护 `jti` 黑名单。

```php
// config/autoload/dependencies.php
return [
    \Goletter\Cas\Contract\TokenManagerInterface::class => \App\Auth\JwtTokenManager::class,
];
```

## 注意事项

- **service 必须完全一致**：跳转登录与校验 ticket 时使用的 `service` 要一字不差；请配置正确的 `service_base_url`。
- **CAS 服务端注册**：回调地址需匹配 `https://api.example.com/cas/callback.*`（带 `redirect` 查询参数）；SLO 也会 POST 到该地址。
- **redirect 白名单**：务必配置 `frontend.allowed_hosts`，防止开放重定向。
- **跨域**：`/cas/token`、`/cas/logout` 由前端 XHR 调用，需配置 CORS；`/cas/login`、`/cas/callback` 为整页跳转。
- **SLO IP 限制**：建议配置 `single_logout.allowed_ips`；经代理时需正确获取真实客户端 IP。
- **Hash 路由**：`code` 会拼在 `#` 前面，前端应从 `location.search` 读取。

## License

MIT
