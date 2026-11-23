# API Reference

The public surface of the package. Behavioural details are linked into the
relevant guides.

Namespace: `InitPHP\Router` (exceptions under `InitPHP\Router\Exception`).

| Type | Kind | Summary |
| --- | --- | --- |
| [`Router`](#router) | class | The router: register routes and dispatch requests. |
| [`Route`](#route) | final class | A matched route, returned by `resolve()`. |
| [`Middleware`](#middleware) | abstract class | Base class for class-based middleware. |
| [`HealthEndpoints`, `HealthRegistry`, `HealthReport`, `CheckResult`](Health-Checks.md) | final classes (`InitPHP\Router\Health`) | Liveness and readiness endpoints. |
| [`ReadinessLinter`, `LintReport`, `LintFinding`, `LintSeverity`](Readiness-Linter.md) | final classes and enum (`InitPHP\Router\Health`) | CI gate for the probe endpoints. |
| [`RouterException`](Exceptions.md) | class | Router runtime errors. |
| [`PageNotFoundException`](Exceptions.md) | class | No route matched and no 404 handler. |
| [`InvalidArgumentException`](Exceptions.md) | class | Invalid argument type/value. |

The internal collaborators (route collection, matcher, dispatcher, middleware
runner, resolvers, link handler, cache, request context) are implementation
details and not part of the supported API.

---

## `Router`

`InitPHP\Router\Router`

### Constructor

```php
public function __construct(
    Psr\Http\Message\RequestInterface $request,
    Psr\Http\Message\ResponseInterface $response,
    array $configs = []
)
```

See [Configuration](Configuration.md) for the `$configs` keys.

### Constants

| Constant | Value | Use |
| --- | --- | --- |
| `Router::BEFORE` | `-1` | Run a filter before the handler. |
| `Router::AFTER` | `1` | Run a filter after the handler. |
| `Router::BOTH` | `0` | Run a filter before **and** after (default). |
| `Router::SUPPORTED_METHODS` | `array` | The methods the router accepts. |

### Registering routes

| Method | Returns | Description |
| --- | --- | --- |
| `register($methods, string $path, $execute, array $options = [])` | `$this` | Register a route for one or more methods (array or `\|`-separated string). |
| `add($methods, string $path, $execute, array $options = [])` | `$this` | Alias of `register()`. |
| `get(string $path, $execute, array $options = [])` | `$this` | Register a GET route. |
| `post(...)` `put(...)` `delete(...)` `options(...)` `patch(...)` `head(...)` `any(...)` | `$this` | Register a route for that method (`any` matches all). |
| `link(string $link, string $source, array $options = [])` | `$this` | Serve a file or directory at a path. See [Static File Links](Static-File-Links.md). |
| `resource(string $prefix, string $controller)` | `void` | Register the seven RESTful routes. See [Controllers](Controllers.md#resource-controllers). |

`$execute` is a closure, a `Controller@method` string, or a `[class, method]`
array. See [Routing](Routing.md) and [Controllers](Controllers.md).

### Grouping routes

| Method | Returns | Description |
| --- | --- | --- |
| `group(string $prefix, \Closure $group, array $options = [])` | `void` | Group routes under a path prefix. |
| `domain(string $host, \Closure $group, array $options = [])` | `void` | Group routes under a host. |
| `port(int $port, \Closure $group, array $options = [])` | `void` | Group routes for a port. |
| `ip($ip, \Closure $group, array $options = [])` | `void` | Group routes for one or more client IPs (`string` or `string[]`). |

Each `$group` callback receives the router. See [Route Groups](Route-Groups.md).

### Names, URLs & patterns

| Method | Returns | Description |
| --- | --- | --- |
| `name(string $name)` | `$this` | Name the most recently registered route. |
| `route(string $name, array $arguments = [])` | `string` | Generate the URL for a named route. |
| `pattern(string $key, string $pattern)` | `$this` | Register a custom `:name` pattern. |
| `where(string $key, string $pattern)` | `$this` | Alias of `pattern()`. |

See [Named Routes & URLs](Named-Routes-and-URLs.md) and
[Route Parameters](Route-Parameters.md).

### Middleware & 404

| Method | Returns | Description |
| --- | --- | --- |
| `filter($filter, int $position = Router::BOTH)` | `$this` | Attach middleware to the last route. |
| `middleware($middleware, int $position = Router::BOTH)` | `$this` | Alias of `filter()`. |
| `setNotFoundHandler($execute, array $options = [])` | `$this` | Define the 404 handler. |
| `error_404($execute, array $options = [])` | `$this` | **Deprecated** alias of `setNotFoundHandler()`. |

See [Middleware](Middleware.md) and [Error Handling](Error-Handling.md).

### Dispatching & introspection

| Method | Returns | Description |
| --- | --- | --- |
| `dispatch()` | `ResponseInterface` | Resolve the current request and run it. |
| `resolve(string $method, string $url)` | [`?Route`](#route) | Return the matching route (or `null`) without running it. |
| `getRoutes(?string $method = null)` | `array` | The registered routes as arrays. |
| `getCurrentController()` | `?string` | Controller class, `'__CALLABLE__'`, or `null`. |
| `getCurrentControllerMethod()` | `?string` | Method name, `''` for closures, or `null`. |
| `getCurrentArguments()` | `array` | The current route's captured arguments. |
| `destroy()` | `void` | Clear all routes and reset state. |

`dispatch()` may throw [`PageNotFoundException`](Exceptions.md),
[`RouterException`](Exceptions.md) or `\ReflectionException`.

---

## `Route`

`InitPHP\Router\Route` — the value object returned by `resolve()`.

```php
$route = $router->resolve('GET', 'http://localhost/users/42');

if ($route !== null) {
    $route->getId();        // int
    $route->getMethods();   // list<string>, e.g. ['GET']
    $route->getPath();      // string, the compiled path pattern
    $route->getHandler();   // string|callable|array
    $route->getArguments(); // list<mixed>, captured values
    $route->getOptions();   // array<string, mixed>
    $route->getOption('name', null); // a single option, with a default
    $route->isLink();       // bool
    $route->toArray();      // array<string, mixed>
}
```

---

## `Middleware`

`InitPHP\Router\Middleware` — extend it to write class-based middleware.

```php
abstract class Middleware
{
    abstract public function before(
        RequestInterface $request,
        ResponseInterface $response,
        array $arguments = []
    ): ?ResponseInterface;

    abstract public function after(
        RequestInterface $request,
        ResponseInterface $response,
        array $arguments = []
    ): ?ResponseInterface;
}
```

Each method returns a response (the new working response), or `null` to halt the
pipeline. See [Middleware](Middleware.md).

---

See also: [Exceptions](Exceptions.md) · [FAQ](FAQ.md).
