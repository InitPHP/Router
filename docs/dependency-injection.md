# Dependency injection

When the router invokes a handler (closure or controller method), it builds the
argument list automatically.

## How arguments are resolved

For each parameter, in order:

1. **Class-typed parameter** (e.g. `RequestInterface $request`):
   - If it matches the current request or response, the **shared** instance is injected.
   - Otherwise, if a [container](#using-a-psr-11-container) is set, the value is fetched from it.
   - Otherwise the class is instantiated, resolving its own constructor dependencies recursively.
2. **Built-in / untyped parameter**: filled from the next captured route value, then a default value, then `null` if the parameter is nullable.

If a required built-in parameter cannot be filled, a
`InitPHP\Router\Exception\RouterException` is thrown.

```php
$router->get('/users/{id}', function (Request $request, Response $response, $id) {
    // $request, $response -> shared instances; $id -> captured value
    return $response->withHeader('X-User', (string) $id);
});
```

## Injecting services

Type-hint a service and it will be constructed (and its dependencies resolved)
for you:

```php
class ReportService
{
    public function __construct(private Clock $clock) {}
}

$router->get('/report', function (ReportService $reports) {
    // $reports is built automatically, with a Clock injected into it
});
```

Constructor parameters that cannot be resolved (e.g. a required scalar with no
value) cause a `RouterException`.

## Using a PSR-11 container

Pass a [PSR-11](https://www.php-fig.org/psr/psr-11/) `ContainerInterface` and the
router will resolve controllers and class-typed dependencies through it:

```php
use Psr\Container\ContainerInterface;

/** @var ContainerInterface $container */
$router = new Router($request, $response, ['container' => $container]);

$router->get('/', 'App\Controllers\HomeController@index');
// HomeController is fetched from the container.
```

When a container is configured, the router asks it for the class first; if the
container returns a non-object, the router falls back to instantiating the class
itself.

## Fresh request/response instances

By default, a parameter type-hinted with your concrete request/response class
receives the **shared** instance. Set `argument_new_instance` to `true` to
inject a freshly constructed instance instead:

```php
$router = new Router($request, $response, ['argument_new_instance' => true]);
```

This only affects request/response-typed parameters; the concrete class must be
constructible for a new instance to be produced.

Next: [Middleware](./middleware.md).
