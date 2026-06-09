# InitPHP Router — Documentation

Welcome to the InitPHP Router documentation. Every page is self-contained and
built around runnable examples.

## Getting around

1. [Installation](./installation.md)
2. [Getting started](./getting-started.md)
3. [Configuration](./configuration.md)

## Routing

4. [Routing](./routing.md) — HTTP verbs, `register()`, `add()`, `any()`
5. [Route parameters & patterns](./route-parameters.md) — `:type` vs `{name}`, custom patterns, optional segments
6. [Route groups](./groups.md) — prefix, domain, port and client-IP grouping
7. [Controllers](./controllers.md) — handler formats and resource controllers
8. [Named routes & URLs](./named-routes.md) — naming routes and generating URLs

## Handling requests

9. [Dependency injection](./dependency-injection.md) — handler arguments and PSR-11 containers
10. [Middleware](./middleware.md) — before/after filters
11. [Static file links](./static-file-links.md) — serving files and directories
12. [Error handling](./error-handling.md) — 404 handling and exceptions
13. [Caching](./caching.md) — caching the compiled route table

## Migrating

14. [Upgrading from 1.x to 2.0](./upgrading-1.x-to-2.0.md)

---

Throughout the docs, `$router` is an instance built like this:

```php
use InitPHP\HTTP\Message\{Request, Response};
use InitPHP\Router\Router;

$router = new Router(Request::createFromGlobals(), new Response());
```

Any PSR-7 request/response pair works; [InitPHP HTTP](https://github.com/InitPHP/HTTP)
is used here for concreteness.
