# Upgrading from 1.x to 2.0

Version 2.0 modernises the internals and tightens the public API. The
registration API (`get()`, `post()`, `group()`, `name()`, `route()`,
`filter()`, …) is unchanged, so most applications upgrade with little or no code
change. The breaking changes are below, each with a migration step.

## At a glance

| Change | Action |
| --- | --- |
| Minimum PHP is now **8.2** | Run on PHP 8.2+ |
| `resolve()` returns a `Route` object, not an array | Use the `Route` getters |
| `INITPHP_ROUTER_CURRENT_*` constants removed | Use the `getCurrent*()` methods |
| Middleware returning `null` halts (no `exit()`) | Review middleware that returned `null` |
| `error_404()` deprecated | Use `setNotFoundHandler()` |
| Client IP is read from `$_SERVER` | Verify your proxy setup |
| The container must implement PSR-11 | Pass a `ContainerInterface` |

## PHP 8.2+

The minimum supported PHP version is **8.2** (the 2.x releases required 8.1; support
for 8.1 was dropped in 3.0.0). Update your environment and your
`composer.json` constraints accordingly.

## `resolve()` returns a `Route`

`resolve()` previously returned an array describing the matched route. It now
returns an [`InitPHP\Router\Route`](API-Reference.md#route) object (or `null` when
nothing matches).

```php
// 1.x
$route   = $router->resolve('GET', $url);
$handler = $route['execute'];
$args    = $route['arguments'];

// 2.0
$route = $router->resolve('GET', $url); // ?Route
if ($route !== null) {
    $handler = $route->getHandler();
    $args    = $route->getArguments();
    // also: getId(), getMethods(), getPath(), getOptions(), isLink()
}
```

Most applications never call `resolve()` directly (they call `dispatch()`), so
this rarely matters.

## Global constants → accessor methods

The `INITPHP_ROUTER_CURRENT_ARGUMENTS`, `INITPHP_ROUTER_CURRENT_CONTROLLER` and
`INITPHP_ROUTER_CURRENT_METHOD` constants are **gone**. Read the same information
from the router after `dispatch()`:

```php
// 1.x
$controller = INITPHP_ROUTER_CURRENT_CONTROLLER;
$method     = INITPHP_ROUTER_CURRENT_METHOD;
$arguments  = INITPHP_ROUTER_CURRENT_ARGUMENTS;

// 2.0
$controller = $router->getCurrentController();       // class, '__CALLABLE__', or null
$method     = $router->getCurrentControllerMethod(); // method, '' for closures, or null
$arguments  = $router->getCurrentArguments();         // array
```

This also makes the router reusable: a single process can now dispatch more than
once without redefining constants.

## Middleware `null` return no longer calls `exit()`

In 1.x, a middleware that returned `null` called `exit()`, terminating the
script. In 2.0 it **halts the pipeline** and returns the current response, which
flows back through `dispatch()` to your emitter — no process-level exit.

```php
// 2.0: returning null stops the pipeline and returns the current response.
public function before($request, $response, array $args): ?ResponseInterface
{
    if ($blocked) {
        return $response->withStatus(403); // preferred: an explicit response
    }
    return $response;
}
```

If you relied on the `exit()` behaviour, return an explicit (non-2xx) response
instead — it halts the pipeline the same way, cleanly.

## `error_404()` → `setNotFoundHandler()`

`error_404()` still works but is **deprecated**. Rename your calls:

```php
// 1.x
$router->error_404($handler);

// 2.0
$router->setNotFoundHandler($handler);
```

## Client IP detection

The client IP used by `ip()` groups is now read from the **server parameters**
(`$_SERVER`), rather than `getenv()`. Releases 2.0 through 3.0.0 read
`HTTP_CLIENT_IP`, then `HTTP_X_FORWARDED_FOR`, then `REMOTE_ADDR`, without
checking that the headers came from a proxy you trust, so the detected address
could be forged. **After 3.0.0** the address is `REMOTE_ADDR`; `X-Forwarded-For` is
read only behind the proxies listed in the
[`trusted_proxies`](Configuration.md#trusted_proxies) option, and `Client-IP` is
never read. If your app sits behind a reverse proxy or load balancer, set
`trusted_proxies`, otherwise `ip()` groups see the proxy's address. See the
warning in [Route Groups](Route-Groups.md#client-ip-groups--ip).

## PSR-11 container

The optional `container` config value is now typed as a
[PSR-11](https://www.php-fig.org/psr/psr-11/) `Psr\Container\ContainerInterface`.
Pass a compliant container:

```php
$router = new Router($request, $response, ['container' => $psr11Container]);
```

## Internal changes (non-breaking)

The implementation was decomposed into focused classes and the private helpers
renamed to camelCase. These are internal details and do not affect the public
API. If you extended `Router` and relied on protected members, review your
subclass.
