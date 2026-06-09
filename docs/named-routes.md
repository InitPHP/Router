# Named routes & URLs

Naming a route lets you generate its URL later without hard-coding paths.

## Naming a route

Use `name()` on the most recently registered route, or the `name` option:

```php
$router->get('/users/{id}', 'UserController@show')->name('users.show');

// equivalent:
$router->get('/users/{id}', 'UserController@show', ['name' => 'users.show']);
```

Names must be unique; a duplicate throws
`InitPHP\Router\Exception\RouterException`.

Inside a group, the `as` option prefixes the names of routes registered in it:

```php
$router->group('/admin', function (Router $route) {
    $route->get('/login', 'AdminController@login')->name('login'); // -> admin.login
}, ['as' => 'admin.']);
```

## Generating URLs with `route()`

`route()` returns the URL for a named route. Pass parameter values keyed by name:

```php
$router->route('users.show', ['id' => 42]);
// => http://your-host/users/42
```

- Values may be scalars or objects implementing `__toString()`.
- If no route has the given name, the name is returned unchanged.

```php
$router->get('/articles/{id}/{slug}', 'ArticleController@show')->name('articles.show');

$router->route('articles.show', ['id' => 7, 'slug' => 'hello-world']);
// => http://your-host/articles/7/hello-world
```

## Optional segments

When a named route has [optional parameters](./route-parameters.md#optional-parameters),
unused optional segments are stripped from the generated URL:

```php
$router->get('/admin/{slug}?', 'AdminController@show')->name('admin');

$router->route('admin');                       // => http://your-host/admin/
$router->route('admin', ['slug' => 'reports']); // => http://your-host/admin/reports
```

Next: [Static file links](./static-file-links.md).
