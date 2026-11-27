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

> **Warning: `domain()` is a routing selector, not an access control.** The
> router reads the host from the request's `Host` header, which the client
> chooses (unless your web server or proxy only serves known host names and
> rewrites the rest). A request that sends `Host: internal.example.com` is
> routed as that host. Do not use a domain group to keep a route private.

## Port groups — `port()`

Match routes by the port of the request URL:

```php
// A second entry point that only exists on port 8443 (for example a separate
// listener for a mobile client). Any caller can reach it by asking for that port.
$router->port(8443, function (Router $route) {
    $route->get('/mobile/ping', 'MobileController@ping');
});
```

> **Warning: `port()` is not an access control.** The port comes from the
> `Host` header (`Host: example.com:8443`), which the client chooses, not from
> the port the connection arrived on. A request that sends that header is routed
> to the group even if your server never listens on that port. Do not put
> `/metrics`, `/admin` or other private routes behind `port()`, `domain()` or
> `ip()`: protect them with authentication middleware or at the network edge
> (separate listener, firewall, reverse proxy rules).

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
[`InvalidArgumentException`](Exceptions.md).

`ip()` groups nest by **intersection**: a nested group can only narrow the access
list, never widen it. Addresses are compared in canonical form, so
`::ffff:203.0.113.10` and `203.0.113.10` are the same address. If the lists have
no address in common, registering the group throws
[`InvalidArgumentException`](Exceptions.md); an empty intersection never means
"everyone".

```php
$router->ip(['203.0.113.10', '203.0.113.11'], function (Router $route) {
    $route->ip('203.0.113.10', function (Router $route) {
        // Only 203.0.113.10; a request from 203.0.113.11 does not match.
        $route->get('/banner-editor', 'BannerController@edit');
    });
});
```

> **Changed in 5.0.0.** Nested `ip()` lists used to be merged by position, so an
> inner `ip(['203.0.113.10'])` inside `ip(['203.0.113.10', '203.0.113.11'])` also
> accepted `203.0.113.11`. An inner list can no longer add addresses: overlapping
> lists are narrowed to the addresses they have in common, and disjoint lists
> throw. Review nested `ip()` groups that relied on the inner list replacing the
> outer one.

See [Upgrading 1.x → 2.0](Upgrading-from-1.x-to-2.0.md#client-ip-detection) for how
the client IP is detected.

The client address is `REMOTE_ADDR`, the address of the TCP peer. `Client-IP` is
never read. `X-Forwarded-For` is read only when `REMOTE_ADDR` is one of the
[`trusted_proxies`](Configuration.md#trusted_proxies) you configured, from right
to left, skipping trusted proxies. Behind a reverse proxy, list the proxy
there; otherwise every request appears to come from the proxy itself.

> **Warning: `ip()` is still a routing selector, not an access control.** With a
> correct `trusted_proxies` list a client cannot choose the address, but the
> guarantee is only as good as that list and your proxy: a range that is too wide
> (or a proxy that passes `X-Forwarded-For` through without appending the peer it
> saw) lets a client forge the address again, and an address shared by many users
> (a NAT, a corporate proxy) identifies all of them. Do not use `ip()` to hide
> `/debug`, `/internal`, health or admin routes. Protect them with authentication
> middleware or at the network edge (firewall, reverse proxy).

## Combining groups

Groups nest freely and combine with all the other options. `prefix` and `as`
accumulate, `ip` narrows to the addresses common to every level, and `middleware`
accumulates (see [Group middleware](Middleware.md#group-middleware)):

```php
$router->domain('{tenant}.example.com', function (Router $route) {
    $route->group('/api', function (Router $route) {
        $route->get('/users', 'Api\UserController@index'); // {tenant}.example.com/api/users
    }, ['as' => 'api.']);
});
```

Next: [Controllers](Controllers.md).

If a group callback throws, the router restores the options of the enclosing level
before the exception propagates, so code that catches it and keeps registering
routes does not inherit the failed group's `prefix`, `host`, `port` or `ip`.
