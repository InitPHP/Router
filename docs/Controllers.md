# Controllers

Instead of closures, a route can point at a controller method.

## Handler formats

All of these reference `HomeController::about()`:

```php
$router->get('/about', 'HomeController@about');
$router->get('/about', 'HomeController::about');
$router->get('/about', 'HomeController->about');
$router->get('/about', [HomeController::class, 'about']);
$router->get('/about', [HomeController::class => 'about']);
```

The controller is instantiated when the route is dispatched, and its
dependencies are resolved automatically (see
[Dependency Injection](Dependency-Injection.md)). Route parameters are passed as
method arguments:

```php
class PostController
{
    public function show(ServerRequest $request, $id)
    {
        return 'Post ' . $id;
    }
}

$router->get('/posts/{id}', 'PostController@show');
```

## Locating controller classes

A fully-qualified class name is used as-is. A short name is resolved against the
configured controller namespace, and optionally loaded from a configured
directory:

```php
$router = new Router($request, $response, [
    'namespaces' => ['controller' => 'App\\Controllers'],
    'paths'      => ['controller' => __DIR__ . '/app/Controllers'],
]);

// Resolves to App\Controllers\HomeController and, if not autoloaded,
// is required from the configured path.
$router->get('/', 'HomeController@index');
```

If a controller cannot be located, a [`RouterException`](Exceptions.md) is thrown.

## Resource controllers

`resource()` registers the seven conventional RESTful routes for a controller in
one call, each with a generated name:

```php
$router->resource('photos', 'PhotoController');
```

| Method | Path | Controller method | Route name |
| --- | --- | --- | --- |
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

A controller may declare middleware via a public `$middlewares` property — see
[Middleware → Controller middlewares](Middleware.md#controller-middlewares).

Next: [Named Routes & URLs](Named-Routes-and-URLs.md).
