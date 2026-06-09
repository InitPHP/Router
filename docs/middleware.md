# Middleware

Middleware (also called *filters*) run **before** and/or **after** a route's
handler. Use them for authentication, logging, CORS, response shaping, etc.

Attach middleware to the **most recently registered** route with `filter()` (or
its alias `middleware()`):

```php
$router->get('/admin', 'AdminController@index')
    ->middleware(AuthMiddleware::class);
```

## Position

The second argument controls when the middleware runs:

| Constant | Runs |
|---|---|
| `Router::BEFORE` | before the handler |
| `Router::AFTER` | after the handler |
| `Router::BOTH` (default) | both |

```php
$router->get('/admin', 'AdminController@index')
    ->middleware(AuthMiddleware::class, Router::BEFORE);
```

## Callable middleware

A middleware can be any callable. It receives the request, the response and the
captured route arguments, and must return a PSR-7 response or `null`:

```php
$router->get('/secret', $handler)->middleware(function ($request, $response, array $args) {
    if (! authenticated($request)) {
        return $response->withStatus(401); // non-2xx -> the handler is skipped
    }
    return $response; // continue
}, Router::BEFORE);
```

## Class middleware

Class middleware extends `InitPHP\Router\Middleware` and implements `before()`
and `after()`:

```php
use InitPHP\Router\Middleware;
use Psr\Http\Message\{RequestInterface, ResponseInterface};

class AuthMiddleware extends Middleware
{
    public function before(RequestInterface $request, ResponseInterface $response, array $arguments = []): ?ResponseInterface
    {
        if (! authenticated($request)) {
            return $response->withStatus(401);
        }
        return $response;
    }

    public function after(RequestInterface $request, ResponseInterface $response, array $arguments = []): ?ResponseInterface
    {
        return $response->withHeader('X-Frame-Options', 'DENY');
    }
}
```

Reference a class by FQCN, or by short name with a configured middleware
namespace/path:

```php
$router = new Router($request, $response, [
    'namespaces' => ['middleware' => 'App\\Middleware'],
    'paths'      => ['middleware' => __DIR__ . '/app/Middleware'],
]);

$router->get('/admin', 'AdminController@index')->middleware('AuthMiddleware');
```

A class middleware is instantiated once and reused, and its own dependencies are
resolved like any other class (see [Dependency injection](./dependency-injection.md)).

## Return values & halting

Every middleware (callable or class) must return:

- a **PSR-7 response** — it replaces the working response. If its status is
  **not** in the 2xx range, the pipeline **halts** and that response is returned
  (the handler and remaining middleware do not run); or
- **`null`** — the pipeline **halts** and the current response is returned as-is.

Returning anything else throws `InitPHP\Router\Exception\RouterException`.

> In 1.x, returning `null` called `exit()`. In 2.0 it halts the pipeline
> cleanly and returns the current response — see the
> [Upgrade guide](./upgrading-1.x-to-2.0.md).

## Controller middlewares

A controller may declare middleware via a public `$middlewares` property. Entries
can be global (applied to every action), keyed `before`/`after`, or scoped to a
specific method:

```php
class AdminController
{
    public array $middlewares = [
        AuthMiddleware::class,                 // before & after, every action
        'before' => [LogMiddleware::class],    // before, every action
        'index'  => [                          // only the index() action
            'before' => [RateLimitMiddleware::class],
        ],
    ];

    public function index() { /* ... */ }
}
```

Next: [Named routes & URLs](./named-routes.md).
