# Caching

Building the route table on every request is cheap, but for large applications
you can cache the compiled table to a file and skip re-registration.

## Enabling the cache

```php
$router = new Router($request, $response, [
    'cache' => [
        'enable' => true,
        'path'   => __DIR__ . '/var/cache/routes.cache',
        'ttl'    => 86400, // seconds (default: 1 day)
    ],
]);
```

## How it works

- On construction, if a fresh (non-expired) cache file exists, the route table is
  loaded from it and the router is marked as **cached**.
- While the router is cached, registration calls (`get()`, `group()`, …) are
  **no-ops** — the routes already came from the cache.
- When the router is destroyed and it was **not** loaded from cache, the current
  route table is written to the cache file.

A typical front controller registers routes unconditionally; on the first
request they are compiled and cached, and on subsequent requests (until the TTL
expires) they are loaded from the cache:

```php
$router = new Router($request, $response, [
    'cache' => ['enable' => true, 'path' => $cacheFile],
]);

// These run normally the first time, and are skipped once cached.
$router->get('/', 'HomeController@index');
$router->resource('photos', 'PhotoController');

$response = $router->dispatch();
```

## Limitations

- **Handlers must be cacheable.** String (`'Controller@method'`) and array
  handlers cache fine. **Closures cannot be serialized**, so don't enable caching
  if your routes use closure handlers.
- Delete the cache file (or wait for the TTL) after changing your routes.
- A write failure is reported as a warning and never interrupts the response.

Next: [Upgrading from 1.x to 2.0](./upgrading-1.x-to-2.0.md).
