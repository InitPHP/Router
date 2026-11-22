# Quick Start

## The lifecycle

The router sits between an incoming PSR-7 **request** and an outgoing PSR-7
**response**. You give it both, register routes, then call `dispatch()`, which
returns the response to emit.

```php
<?php
require_once 'vendor/autoload.php';

use InitPHP\HTTP\Message\{ServerRequest, Response};
use InitPHP\HTTP\Emitter\Emitter;
use InitPHP\Router\Router;

// 1. Build the request and a blank response.
$request  = ServerRequest::createFromGlobals();
$response = new Response();

// 2. Create the router.
$router = new Router($request, $response);

// 3. Register routes.
$router->get('/', function () {
    return 'Hello World!';
});

$router->post('/login', function (ServerRequest $request, Response $response) {
    return $response->withStatus(401);
});

// 4. (Optional) customise the 404 response.
$router->setNotFoundHandler(function () {
    return 'Page Not Found';
});

// 5. Resolve the current request and emit the response.
(new Emitter())->emit($router->dispatch());
```

## What a handler can return

The matched handler is invoked and its result is folded into the response:

| Return value | Behaviour |
| --- | --- |
| `string` (or anything stringable) | Written to the response body |
| A PSR-7 `ResponseInterface` | Used as the new response |
| A PSR-7 `StreamInterface` | Set as the response body |
| `null` / nothing | Any echoed output is captured into the body |

```php
$router->get('/text', fn () => 'plain text');

$router->get('/json', function (Response $response) {
    $response->getBody()->write('{"ok":true}');
    return $response->withHeader('Content-Type', 'application/json');
});

$router->get('/echo', function () {
    echo 'captured output';
});
```

## Handler arguments

Parameters are resolved automatically — class-typed parameters get the shared
request/response (or a service), and the rest are filled from the captured path
values:

```php
$router->get('/users/{id}', function (ServerRequest $request, $id) {
    return 'User ' . $id;
});
```

See [Dependency Injection](Dependency-Injection.md) for the full rules.

## When nothing matches

If no route matches and no 404 handler is set, `dispatch()` throws
[`PageNotFoundException`](Exceptions.md). Define a handler to control the response —
see [Error Handling](Error-Handling.md).

Next: [Configuration](Configuration.md).
