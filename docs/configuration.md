# Configuration

The third constructor argument is a configuration array. Every key is optional.

```php
use InitPHP\Router\Router;

$router = new Router($request, $response, [
    'paths' => [
        'controller' => null, // Absolute path to the directory holding controller classes.
        'middleware' => null, // Absolute path to the directory holding middleware classes.
    ],
    'namespaces' => [
        'controller' => null, // Namespace prefix for controller classes, if any.
        'middleware' => null, // Namespace prefix for middleware classes, if any.
    ],
    'base_path'             => '/',   // Sub-directory the app runs in.
    'variable_method'       => false, // Enable $_REQUEST['_method'] override.
    'argument_new_instance' => false, // Inject fresh instances instead of the shared request/response.

    // Optional integrations:
    'container' => $psr11Container,   // A PSR-11 ContainerInterface.
    'cache'     => [
        'enable' => false,
        'path'   => null,             // File to read/write the compiled route table.
        'ttl'    => 86400,            // Seconds.
    ],
]);
```

## Keys

### `paths` / `namespaces`

Used to locate controller and middleware classes that are referenced by short
name. See [Controllers](./controllers.md) and [Middleware](./middleware.md).

### `base_path`

If the application is served from a sub-directory (e.g. `https://host/app/`),
set `base_path` to `'/app'` so route paths are matched relative to it. A value
of `'/'` (the default) means the app runs at the domain root.

### `variable_method`

When `true`, the HTTP method can be overridden with a `_method` request
parameter — useful for HTML forms, which can only send GET/POST:

```php
$router = new Router($request, $response, ['variable_method' => true]);
$router->put('/articles/{id}', 'ArticleController@update');
```

```html
<form method="post" action="/articles/42">
    <input type="hidden" name="_method" value="PUT">
    <!-- ... -->
</form>
```

The override is only honoured when the value is one of the supported methods.

### `argument_new_instance`

By default, a handler parameter type-hinted with your request/response class
receives the **shared** request/response. Set this to `true` to instead inject a
**newly constructed** instance of that class. See
[Dependency injection](./dependency-injection.md).

### `container`

A [PSR-11](https://www.php-fig.org/psr/psr-11/) `ContainerInterface`. When set,
the router resolves controllers and class-typed handler dependencies through it.
See [Dependency injection](./dependency-injection.md).

### `cache`

Caches the compiled route table to a file. See [Caching](./caching.md).

Next: [Routing](./routing.md).
