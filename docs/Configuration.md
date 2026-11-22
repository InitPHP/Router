# Configuration

The third constructor argument is a configuration array. Every key is optional.

```php
use InitPHP\Router\Router;

$router = new Router($request, $response, [
    'paths' => [
        'controller' => null, // Absolute path to the controller directory.
        'middleware' => null, // Absolute path to the middleware directory.
    ],
    'namespaces' => [
        'controller' => null, // Namespace prefix for controller classes.
        'middleware' => null, // Namespace prefix for middleware classes.
    ],
    'base_path'             => '/',   // Sub-directory the app runs in.
    'variable_method'       => false, // Enable the $_REQUEST['_method'] override.
    'argument_new_instance' => false, // Inject fresh request/response instances.

    // Optional integrations:
    'container' => $psr11Container,   // A PSR-11 ContainerInterface.
    'cache'     => [
        'enable' => false,
        'path'   => null,             // File for the compiled route table.
        'ttl'    => 86400,            // Seconds.
    ],
]);
```

## Keys

### `paths` / `namespaces`

Used to locate controller and middleware classes referenced by short name. See
[Controllers](Controllers.md) and [Middleware](Middleware.md).

### `base_path`

If the app is served from a sub-directory (e.g. `https://host/app/`), set
`base_path` to `'/app'` so route paths match relative to it. The default `'/'`
means the app runs at the domain root.

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
</form>
```

The override is only honoured when the value is one of the supported methods.

### `argument_new_instance`

By default, a handler parameter type-hinted with your request/response class
receives the **shared** instance. Set this to `true` to inject a **newly
constructed** instance instead. See [Dependency Injection](Dependency-Injection.md).

### `container`

A [PSR-11](https://www.php-fig.org/psr/psr-11/) `ContainerInterface`. When set,
controllers and class-typed handler dependencies are resolved through it. See
[Dependency Injection](Dependency-Injection.md).

### `cache`

Caches the compiled route table to a file. See [Caching](Caching.md).

Next: [Routing](Routing.md).
