# InitPHP Router

A fast, framework-agnostic HTTP router for PHP. Define expressive routes for any
HTTP method, group them by prefix, domain, port or client IP, attach middleware,
inject dependencies into your handlers, generate URLs from named routes and
serve static files — all on top of any [PSR-7](https://www.php-fig.org/psr/psr-7/)
request/response implementation.

[![Latest Stable Version](http://poser.pugx.org/initphp/router/v)](https://packagist.org/packages/initphp/router) [![Total Downloads](http://poser.pugx.org/initphp/router/downloads)](https://packagist.org/packages/initphp/router) [![License](http://poser.pugx.org/initphp/router/license)](https://packagist.org/packages/initphp/router) [![PHP Version Require](http://poser.pugx.org/initphp/router/require/php)](https://packagist.org/packages/initphp/router)

## Features

- GET, POST, PUT, DELETE, OPTIONS, PATCH, HEAD and ANY methods (plus virtual `LINK` routes).
- Optional Laravel-style method override via `$_REQUEST['_method']`.
- Controller handlers (`Home@about`, `Home::about`, `Home->about`, or `[Home::class, 'about']`).
- Before/after middleware (filters), per route and per controller.
- Static and dynamic route patterns, with a customizable pattern registry.
- Route grouping by prefix, domain, port and client IP.
- Named routes and URL generation.
- Reflection-based dependency injection for handlers, with optional [PSR-11](https://www.php-fig.org/psr/psr-11/) container support.
- Customizable 404 handling.
- Serve files or whole directories as virtual links (with path-traversal protection).
- Optional route caching.

## Requirements

- PHP 8.1 or later
- Any [PSR-7](https://www.php-fig.org/psr/psr-7/) HTTP message implementation and a PSR-7 emitter. The examples below use [InitPHP HTTP](https://github.com/InitPHP/HTTP).
- For pretty URLs, a front controller (`index.php`) with URL rewriting (see below).

## Installation

```bash
composer require initphp/router
```

The examples in the documentation use the InitPHP HTTP library:

```bash
composer require initphp/http
```

### Web server

Apache (`.htaccess`):

```apacheconf
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [L]
```

NGINX:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

## Quick start

```php
<?php
require_once 'vendor/autoload.php';

use InitPHP\HTTP\Message\{Request, Response};
use InitPHP\HTTP\Emitter\Emitter;
use InitPHP\Router\Router;

$request  = Request::createFromGlobals();
$response = new Response();

$router = new Router($request, $response);

$router->get('/', function () {
    return 'Hello World!';
});

$router->post('/login', function (Request $request, Response $response) {
    return $response->withStatus(401);
});

// Optional: customise the 404 response (otherwise a PageNotFoundException is thrown).
$router->setNotFoundHandler(function () {
    return 'Page Not Found';
});

// Resolve the current request and build the response.
$response = $router->dispatch();

// Emit it.
(new Emitter())->emit($response);
```

## Documentation

Full, example-driven documentation lives in [`docs/`](./docs/README.md):

- [Installation](./docs/installation.md) · [Getting started](./docs/getting-started.md) · [Configuration](./docs/configuration.md)
- [Routing](./docs/routing.md) · [Route parameters & patterns](./docs/route-parameters.md) · [Route groups](./docs/groups.md)
- [Controllers](./docs/controllers.md) · [Dependency injection](./docs/dependency-injection.md) · [Middleware](./docs/middleware.md)
- [Named routes & URLs](./docs/named-routes.md) · [Static file links](./docs/static-file-links.md)
- [Error handling](./docs/error-handling.md) · [Caching](./docs/caching.md)
- [Upgrading from 1.x to 2.0](./docs/upgrading-1.x-to-2.0.md)

## Testing

```bash
composer test            # PHPUnit
composer phpstan         # static analysis (level max)
composer cs:check        # coding standards (PSR-12)
composer coverage:check  # line coverage with an enforced floor
```

## Contributing

Contributions are welcome. Please read the org-wide
[Contributing guide](https://github.com/InitPHP/.github/blob/main/CONTRIBUTING.md)
and the [Code of Conduct](https://github.com/InitPHP/.github/blob/main/CODE_OF_CONDUCT.md)
before opening a pull request.

## Credits

- [Muhammet ŞAFAK](https://www.muhammetsafak.com.tr) <<info@muhammetsafak.com.tr>>

## License

Released under the [MIT License](./LICENSE).
