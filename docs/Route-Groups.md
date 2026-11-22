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
$router->ip('127.0.0.1', function (Router $route) {
    $route->get('/debug', 'DebugController@index');
});

$router->ip(['10.0.0.1', '10.0.0.2'], function (Router $route) {
    $route->get('/internal', 'InternalController@index');
});
```

Requests from any other address simply do not match these routes, so they fall
through to a 404 unless another route matches. An invalid IP address throws
[`InvalidArgumentException`](Exceptions.md). See
[Upgrading 1.x → 2.0](Upgrading-from-1.x-to-2.0.md#client-ip-detection) for how the
client IP is detected.

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
