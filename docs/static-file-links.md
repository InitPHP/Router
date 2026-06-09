# Static file links

`link()` exposes a file — or all files inside a directory — at a URL path. The
file is streamed into the response with appropriate `Content-Type` and
`Content-Length` headers.

## Linking a single file

```php
$router->link('/favicon.ico', __DIR__ . '/assets/favicon.ico');
```

A request to `/favicon.ico` serves that file.

## Linking a directory

When the source is a directory, requests below the link path map to files inside
it:

```php
$router->link('/assets', __DIR__ . '/public/assets');
```

- `/assets/css/app.css` → `public/assets/css/app.css`
- `/assets/img/logo.png` → `public/assets/img/logo.png`

### Path-traversal protection

Directory links are protected against traversal: the resolved real path must
stay **inside** the link root. A request such as `/assets/../secret.txt` does not
match and falls through to a 404 — it can never escape the directory.

## Notes

- `link()` throws `InitPHP\Router\Exception\InvalidArgumentException` if the
  source path does not exist.
- Link routes are considered for **every** HTTP method.
- A request that matches a link but whose target file is missing falls through to
  404 handling (see [Error handling](./error-handling.md)).

Next: [Error handling](./error-handling.md).
