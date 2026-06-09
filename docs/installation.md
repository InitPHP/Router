# Installation

## Requirements

- PHP **8.1** or later
- A [PSR-7](https://www.php-fig.org/psr/psr-7/) HTTP message implementation
- A PSR-7 emitter (to send the response to the client)

## Install with Composer

```bash
composer require initphp/router
```

The router does not ship a PSR-7 implementation. Any compliant library works;
the documentation uses [InitPHP HTTP](https://github.com/InitPHP/HTTP):

```bash
composer require initphp/http
```

## Front controller & URL rewriting

Route everything through a single `index.php` so the router can match any path.

**Apache** — `public/.htaccess`:

```apacheconf
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [L]
```

**NGINX**:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

## Optional dependencies

- A [PSR-11](https://www.php-fig.org/psr/psr-11/) container (`psr/container`) if
  you want the router to resolve controllers/services from your container. See
  [Dependency injection](./dependency-injection.md).

Next: [Getting started](./getting-started.md).
