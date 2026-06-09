# Controllers

Instead of closures, a route can point at a controller method.

## Handler formats

All of these are equivalent ways to reference `HomeController::about()`:

```php
$router->get('/about', 'HomeController@about');
$router->get('/about', 'HomeController::about');
$router->get('/about', 'HomeController->about');
$router->get('/about', [HomeController::class, 'about']);
$router->get('/about', [HomeController::class => 'about']);
```

The controller is instantiated when the route is dispatched, and its
dependencies are resolved automatically (see
[Dependency injection](./dependency-injection.md)). Route parameters are passed
as method arguments:

```php
class PostController
{
    public function show(Request $request, $id)
    {
        return 'Post ' . $id;
    }
}

$router->get('/posts/{id}', 'PostController@show');
```

## Locating controller classes

If you reference a controller by its fully-qualified class name, it is used
as-is. If you reference it by short name, configure a namespace (and optionally
a directory to load it from):

```php
$router = new Router($request, $response, [
    'namespaces' => ['controller' => 'App\\Controllers'],
    'paths'      => ['controller' => __DIR__ . '/app/Controllers'],
]);

// Resolves to App\Controllers\HomeController and, if not autoloaded,
// is required from the configured path.
$router->get('/', 'HomeController@index');
```

If a controller cannot be located, a
`InitPHP\Router\Exception\RouterException` is thrown.

## Resource controllers

`resource()` registers the seven conventional RESTful routes for a controller in
one call, each with a generated name:

```php
$router->resource('photos', 'PhotoController');
```

| Method | Path | Controller method | Route name |
|---|---|---|---|
| GET | `/photos` | `index` | `photos.index` |
| GET | `/photos/create` | `create` | `photos.create` |
| POST | `/photos` | `store` | `photos.store` |
| GET | `/photos/{photos}` | `show` | `photos.show` |
| GET | `/photos/{photos}/edit` | `edit` | `photos.edit` |
| PUT, PATCH | `/photos/{photos}` | `update` | `photos.update` |
| DELETE | `/photos/{photos}` | `destroy` | `photos.destroy` |

The controller is resolved using the configured controller namespace/path, so
`'PhotoController'` works with the configuration shown above.

## Per-controller middleware

A controller may declare middleware via a public `$middlewares` property —
see [Middleware](./middleware.md#controller-middlewares).

Next: [Dependency injection](./dependency-injection.md).
