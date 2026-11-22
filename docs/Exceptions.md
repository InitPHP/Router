# Exceptions

All router exceptions live under the `InitPHP\Router\Exception` namespace.

| Exception | Extends | Thrown when |
| --- | --- | --- |
| `RouterException` | `\RuntimeException` | A controller/middleware can't be resolved, a route name is duplicated, a filter is invalid, a parameter can't be resolved, the route cache can't be read, etc. |
| `PageNotFoundException` | `RouterException` | No route matched the request and no 404 handler is defined. |
| `InvalidArgumentException` | `\InvalidArgumentException` | An argument has an invalid type or value (an unsupported HTTP method, a bad handler type, an invalid IP address, a missing link source). |

## Hierarchy

```
\RuntimeException
 └─ InitPHP\Router\Exception\RouterException
     └─ InitPHP\Router\Exception\PageNotFoundException

\InvalidArgumentException
 └─ InitPHP\Router\Exception\InvalidArgumentException
```

Because `PageNotFoundException` extends `RouterException`, catching
`RouterException` also catches 404s — order your `catch` blocks accordingly.

## Handling them

```php
use InitPHP\Router\Exception\PageNotFoundException;
use InitPHP\Router\Exception\RouterException;

try {
    $response = $router->dispatch();
} catch (PageNotFoundException $e) {
    // No route matched and no 404 handler was set.
    $response = $response->withStatus(404);
} catch (RouterException $e) {
    // Any other router runtime error.
    $response = $response->withStatus(500);
}
```

The cleanest way to render a 404 is to register a handler with
`setNotFoundHandler()` so no exception is thrown at all — see
[Error Handling](Error-Handling.md).

See also: [API Reference](API-Reference.md) · [FAQ](FAQ.md).
