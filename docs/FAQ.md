# FAQ

Quick answers to questions that come up when adopting the package. If yours is
not here, please [open an issue](https://github.com/InitPHP/Router/issues/new) —
documentation fixes are reviewed eagerly.

## General

### Does it need a framework?

No. The router is framework-agnostic: you give it a PSR-7 request and response,
and it returns a PSR-7 response. Use it standalone or inside any stack.

### Which PSR-7 implementation should I use?

Any compliant one. The package requires `psr/http-message` (`^1.1 || ^2.0`); the
examples use [InitPHP HTTP](https://github.com/InitPHP/HTTP), but Nyholm,
Guzzle, Laminas, etc. all work. You also need a PSR-7 *emitter* to send the
response.

### What PHP version is required?

PHP **8.2** or later.

## Routing

### Why does `/users/{id}` match `/users/abc`?

Because the `{name}` syntax matches **any** non-slash segment — the name is just
a label. To constrain to digits, use the typed form `:id`
(`/users/:id`). See [Route Parameters](Route-Parameters.md).

### How do I read the captured path values?

They are passed to your handler as arguments (after any injected
request/response), in order:

```php
$router->get('/posts/{id}/{slug}', function ($id, $slug) { /* ... */ });
```

After dispatching, you can also read them with `getCurrentArguments()`.

### Two routes match the same URL — which one wins?

The most specific match: routes are ranked by path length and by the number of
captured parameters, and the highest-ranked match is used.

### How do I apply middleware to a whole group?

Attach it per route with `filter()`/`middleware()`, declare it on the controller
via the `$middlewares` property, or pass a `middleware` option — group options
propagate to every route registered in the group:

```php
$router->group('/admin', function (Router $route) {
    $route->get('/', 'AdminController@index');
    $route->get('/users', 'AdminController@users');
}, ['middleware' => [AuthMiddleware::class]]);
```

Middleware options accumulate across nested groups and routes; see
[Group middleware](Middleware.md#group-middleware).

## Behaviour

### Can I dispatch the same router more than once?

Yes. In 2.0 the router holds its "current route" information in instance state
(via `getCurrentController()` / `getCurrentControllerMethod()` /
`getCurrentArguments()`), so a single process — or a single test run — can
dispatch repeatedly. (1.x used global constants and could only dispatch once.)

### Can I cache routes that use closures?

No. Closures cannot be serialized, so enable caching only when your handlers are
`Controller@method` strings or arrays. See [Caching](Caching.md).

### A middleware returned `null` — what happens?

The pipeline halts and the current response is returned as-is. It does **not**
call `exit()` (that was the 1.x behaviour). Prefer returning an explicit
non-2xx response to stop the pipeline. See
[Upgrading 1.x → 2.0](Upgrading-from-1.x-to-2.0.md).

## Migrating

### I'm upgrading from 1.x — what changed?

See the dedicated [Upgrading 1.x → 2.0](Upgrading-from-1.x-to-2.0.md) guide. The
registration API is unchanged; the notable changes are a higher PHP floor,
`resolve()` returning a `Route` object, the removal of the global constants, and
the `error_404()` → `setNotFoundHandler()` rename.

See also: [API Reference](API-Reference.md) · [Exceptions](Exceptions.md).
