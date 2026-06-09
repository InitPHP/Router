# Routing

## HTTP verb methods

Each supported method has a dedicated helper. They all share the signature
`(string $path, $handler, array $options = [])` and return the router for
chaining.

```php
$router->get('/',            $handler);
$router->post('/login',      $handler);
$router->put('/users/{id}',  $handler);
$router->patch('/users/{id}', $handler);
$router->delete('/users/{id}', $handler);
$router->options('/users',   $handler);
$router->head('/health',     $handler);
$router->any('/webhook',     $handler); // matches every HTTP method
```

`$handler` may be a closure, a `Controller@method` string, or an array — see
[Controllers](./controllers.md).

## `register()` and `add()`

`register()` (aliased as `add()`) registers a route for one or more methods. The
methods can be an array or a `|`-separated string:

```php
$router->register(['GET', 'POST'], '/search', $handler);
$router->add('GET|POST', '/search', $handler);
```

Supported methods: `GET`, `POST`, `PUT`, `DELETE`, `HEAD`, `PATCH`, `OPTIONS`,
`ANY`, `LINK`. An unsupported method throws
`InitPHP\Router\Exception\InvalidArgumentException`.

## The fluent API

Most registration methods return `$this`, so calls chain — including the
modifiers `name()`, `filter()`/`middleware()`, `pattern()`/`where()`:

```php
$router->get('/articles/{id}', 'ArticleController@show')
    ->where('id', '[0-9]+')
    ->name('articles.show')
    ->middleware(AuthMiddleware::class);
```

`name()`, `filter()` and `middleware()` apply to the **most recently registered**
route.

## `any()` and method matching

When resolving a request, candidate routes are: the routes registered for the
request method, plus all `ANY` routes, plus all `LINK` routes (see
[Static file links](./static-file-links.md)). When several routes match the same
URL, the most specific one wins (longer paths and more captured parameters rank
higher).

## Inspecting registered routes

`getRoutes()` returns the registered routes as plain arrays — handy for
debugging or building a route list:

```php
$all      = $router->getRoutes();        // grouped by HTTP method
$gets     = $router->getRoutes('GET');   // only GET routes, keyed by path
```

Next: [Route parameters & patterns](./route-parameters.md).
