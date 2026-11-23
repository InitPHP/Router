# InitPHP Router — Documentation

Welcome to the official documentation for **`initphp/router`** — a fast,
framework-agnostic HTTP router for PHP. It turns an incoming
[PSR-7](https://www.php-fig.org/psr/psr-7/) request into a response by matching
it against the routes you define, then running it through a middleware/handler
pipeline.

```bash
composer require initphp/router
```

```php
use InitPHP\HTTP\Message\{ServerRequest, Response};
use InitPHP\HTTP\Emitter\Emitter;
use InitPHP\Router\Router;

$router = new Router(ServerRequest::createFromGlobals(), new Response());

$router->get('/', fn () => 'Hello World!');
$router->get('/posts/{id}', fn ($id) => 'Post #' . $id);

(new Emitter())->emit($router->dispatch());
```

> The examples in this documentation use [InitPHP HTTP](https://github.com/InitPHP/HTTP)
> for concreteness, but any PSR-7 request/response implementation works.

## What it does

| Capability | Where to read |
| --- | --- |
| HTTP verbs, `register()`/`add()`, `any()` | [Routing](Routing.md) |
| Typed & free-form path parameters, custom patterns | [Route Parameters](Route-Parameters.md) |
| Group routes by prefix, domain, port and client IP | [Route Groups](Route-Groups.md) |
| Controller handlers and resource controllers | [Controllers](Controllers.md) |
| Name routes and generate their URLs | [Named Routes & URLs](Named-Routes-and-URLs.md) |
| Inject the request/response and services into handlers | [Dependency Injection](Dependency-Injection.md) |
| Before/after middleware (filters) | [Middleware](Middleware.md) |
| Serve files and directories | [Static File Links](Static-File-Links.md) |
| Custom 404 handling and the exception hierarchy | [Error Handling](Error-Handling.md) · [Exceptions](Exceptions.md) |
| Cache the compiled route table | [Caching](Caching.md) |
| Liveness and readiness probe endpoints | [Health Checks](Health-Checks.md) |
| CI gate for the probe endpoints | [Readiness Linter](Readiness-Linter.md) |

## Requirements

- **PHP 8.2+**
- A PSR-7 HTTP message implementation and a PSR-7 emitter
- Optional: a [PSR-11](https://www.php-fig.org/psr/psr-11/) container for service resolution

## Highlights

- **Framework-agnostic** — speaks PSR-7 in and out; drop it into any stack.
- **Expressive routing** — typed parameters (`:id`, `:uuid`, …), custom
  patterns, optional segments, and nested groups by prefix/domain/port/IP.
- **Pipeline** — before/after middleware per route and per controller, with a
  clean halt mechanism (no `exit()`).
- **Dependency injection** — handler arguments are resolved by reflection, with
  optional PSR-11 container support.
- **Typed & tested** — fully typed, analysed at PHPStan's max level, and covered
  by an extensive test suite.

## Where to go next

New here? Start with [Installation](Installation.md) and the
[Quick Start](Quick-Start.md). Upgrading an existing project? See
[Upgrading 1.x → 2.0](Upgrading-from-1.x-to-2.0.md).
