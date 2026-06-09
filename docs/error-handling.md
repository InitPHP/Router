# Error handling

## 404 — no matching route

If no route matches the request and no 404 handler is defined, `dispatch()`
throws `InitPHP\Router\Exception\PageNotFoundException`.

Define a handler with `setNotFoundHandler()` to control the response instead. The
handler is a closure or controller reference, just like a route handler, and the
response is returned with status **404**:

```php
$router->setNotFoundHandler(function (Request $request, Response $response) {
    $response->getBody()->write('Not Found');
    return $response; // dispatch() returns this with a 404 status
});

// or a controller:
$router->setNotFoundHandler('ErrorController@notFound');
```

> `error_404()` is a deprecated alias of `setNotFoundHandler()`, kept for
> backwards compatibility. See the [Upgrade guide](./upgrading-1.x-to-2.0.md).

## Exception hierarchy

All router exceptions live under `InitPHP\Router\Exception`:

| Exception | Extends | Thrown when |
|---|---|---|
| `RouterException` | `\RuntimeException` | a controller/middleware can't be resolved, a route name is duplicated, a filter is invalid, etc. |
| `PageNotFoundException` | `RouterException` | no route matched and no 404 handler is set |
| `InvalidArgumentException` | `\InvalidArgumentException` | an argument has an invalid type/value (unsupported method, bad handler, invalid IP, missing link source) |

Catch `RouterException` to handle both router runtime errors and 404s:

```php
use InitPHP\Router\Exception\PageNotFoundException;
use InitPHP\Router\Exception\RouterException;

try {
    $response = $router->dispatch();
} catch (PageNotFoundException $e) {
    $response = $response->withStatus(404);
} catch (RouterException $e) {
    $response = $response->withStatus(500);
}

(new Emitter())->emit($response);
```

## Inspecting the dispatched route

After `dispatch()`, you can inspect what handled the request:

```php
$router->getCurrentController();        // controller class, '__CALLABLE__', or null
$router->getCurrentControllerMethod();  // method name, '' for closures, or null
$router->getCurrentArguments();         // the captured route arguments
```

Next: [Caching](./caching.md).
