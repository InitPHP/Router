# Route Groups

Groups apply a shared concern — a path prefix, a domain, a port or a client-IP
restriction — to every route registered inside a callback. The callback receives
the router.

Groups can be nested, and nested prefixes/name-prefixes accumulate.

## Prefix groups — `group()`

```php
$router->group('/admin', function (Router $route) {
    $route->get('/', 'AdminController@dashboard');  // /admin/
    $route->get('/users', 'AdminController@users'); // /admin/users
});
```

Nested groups combine their prefixes:

```php
$router->group('/admin', function (Router $route) {
    $route->group('/posts', function (Router $route) {
        $route->get('/', 'PostController@index');         // /admin/posts/
        $route->delete('/{id}', 'PostController@delete'); // /admin/posts/{id}
    });
});
```

Pass an `as` option to prefix the **names** of routes in the group (see
[Named Routes & URLs](Named-Routes-and-URLs.md)):

```php
$router->group('/admin', function (Router $route) {
    $route->get('/login', 'AdminController@login')->name('login'); // name: admin.login
}, ['as' => 'admin.']);
```

## Domain groups — `domain()`

Match routes against a host, including host parameters:

```php
$router->domain('{slug}.example.com', function (Router $route) {
    $route->get('/', 'TenantController@home');
});
```

A request to `https://acme.example.com/` matches and captures `acme`.

## Port groups — `port()`

Restrict routes to a specific port:

```php
$router->port(9000, function (Router $route) {
    $route->get('/metrics', 'MetricsController@index');
});
```

## Client-IP groups — `ip()`

Only match for requests coming from one or more client IP addresses:

```php
// Pick a variant of a page for known addresses. Anyone who can forge the
// address (see the warning below) merely sees the staff variant, so this must
// never gate anything that matters.
$router->ip('203.0.113.10', function (Router $route) {
    $route->get('/banner', 'BannerController@staff');
});

$router->ip(['203.0.113.10', '203.0.113.11'], function (Router $route) {
    $route->get('/banner-preview', 'BannerController@preview');
});
```

Requests from any other address simply do not match these routes, so they fall
through to a 404 unless another route matches. An invalid IP address throws
[`InvalidArgumentException`](Exceptions.md). See
[Upgrading 1.x → 2.0](Upgrading-from-1.x-to-2.0.md#client-ip-detection) for how the
client IP is detected.

> **Warning: `ip()` is not an access control.** The router takes the client
> address from the `Client-IP` header first, then the first entry of
> `X-Forwarded-For`, and only then from `REMOTE_ADDR` (`$_SERVER['HTTP_CLIENT_IP']`,
> `HTTP_X_FORWARDED_FOR`, `REMOTE_ADDR`). It does not check that those headers
> came from a proxy you trust, so a client that sends `Client-IP: 127.0.0.1` is
> routed as `127.0.0.1`. Do not use `ip()` to hide `/debug`, `/internal`,
> health or admin routes. Protect them with authentication middleware or at the
> network edge (firewall, reverse proxy), and have the edge remove or overwrite
> `Client-IP` and `X-Forwarded-For` on inbound requests so the application never
> sees a client-supplied value.

## Combining groups

Groups nest freely and combine with all the other options:

```php
$router->domain('{tenant}.example.com', function (Router $route) {
    $route->group('/api', function (Router $route) {
        $route->get('/users', 'Api\UserController@index'); // {tenant}.example.com/api/users
    }, ['as' => 'api.']);
});
```

Next: [Controllers](Controllers.md).
