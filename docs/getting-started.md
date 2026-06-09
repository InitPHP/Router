# Getting started

## The request/response lifecycle

The router sits between an incoming PSR-7 **request** and an outgoing PSR-7
**response**. You give it both, register routes, then call `dispatch()`, which
returns the response to emit.

```php
<?php
require_once 'vendor/autoload.php';

use InitPHP\HTTP\Message\{Request, Response};
use InitPHP\HTTP\Emitter\Emitter;
use InitPHP\Router\Router;

// 1. Build the request and a blank response.
$request  = Request::createFromGlobals();
$response = new Response();

// 2. Create the router.
$router = new Router($request, $response);

// 3. Register routes.
$router->get('/', function () {
    return 'Hello World!';
});

$router->get('/posts/{id}', function ($id) {
    return 'Post #' . $id;
});

// 4. Resolve the current request into a response.
$response = $router->dispatch();

// 5. Emit it.
(new Emitter())->emit($response);
```

## What a handler can return

Inside `dispatch()` the matched handler is invoked and its result is folded into
the response:

| Return value | Behaviour |
|---|---|
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

Handler parameters are resolved automatically:

- **Class-typed** parameters receive the shared request/response (or a service —
  see [Dependency injection](./dependency-injection.md)).
- **Other** parameters are filled, in order, from the values captured in the
  route path.

```php
$router->get('/users/{id}', function (Request $request, $id) {
    // $request -> the current request, $id -> the captured path segment
    return 'User ' . $id;
});
```

## What happens when nothing matches

If no route matches and you have **not** set a 404 handler, `dispatch()` throws
`InitPHP\Router\Exception\PageNotFoundException`. Define a handler to control the
response instead — see [Error handling](./error-handling.md):

```php
$router->setNotFoundHandler(function (Request $request, Response $response) {
    return $response->withStatus(404);
});
```

Next: [Configuration](./configuration.md).
