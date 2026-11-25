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
    'variable_method'       => false, // Enable the POST `_method` override.
    'trusted_proxies'       => [],    // Proxies whose X-Forwarded-For header is trusted.
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
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
</form>
```

The override is only honoured when the real request method is `POST` and the
value is one of the supported methods. It is read from the POST parameters only:
the parsed body of a PSR-7 server request when it is an array, otherwise
`$_POST`. A `_method` in the query string, or on a `GET` request, is ignored.

Check the token before the handler changes anything, in the handler or in a
middleware (the session must be started and hold the token you rendered into the
form):

```php
function assertValidCsrfToken(): void
{
    $expected = (string) ($_SESSION['csrf_token'] ?? '');
    $sent     = (string) ($_POST['csrf_token'] ?? ''); // request body only, never the query string

    if ($expected === '' || !hash_equals($expected, $sent)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}
```

> **Changed in 4.0.0: the override no longer applies to `GET` requests.**
> Releases up to 3.0.0 read `_method` from `$_REQUEST` (query string and body) and did not
> require a real `POST`, so a link such as
> `https://example.com/articles/42?_method=DELETE` was routed as `DELETE`. Now a
> request that is not a `POST`, and a `_method` that is not in the POST body, is
> routed under its real method. A client that relied on `GET ...?_method=PUT` must
> send a `POST` with the field in the body.
>
> The override is still not a substitute for a CSRF token: a cross-site HTML form
> can submit a `POST` with `_method=DELETE` in its body, so keep the token check
> above on every state-changing route. A PSR-7 server request whose parsed body
> comes from a JSON payload (`{"_method": "DELETE"}`) is read the same way. With a
> PSR-7 server request only its parsed body counts, never `$_POST`.
>
> Leave `variable_method` off (the default) for JSON APIs and any app that does
> not submit HTML forms.

### `trusted_proxies`

A list of IP addresses and CIDR ranges (IPv4 or IPv6) of the reverse proxies and
load balancers in front of the application. It decides where the client address
used by [`ip()` groups](Route-Groups.md#client-ip-groups--ip) comes from:

```php
$router = new Router($request, $response, [
    'trusted_proxies' => ['192.0.2.5', '10.0.5.0/28'], // your proxies only, never a whole internal network
]);
```

- By default the list is empty: the client address is `REMOTE_ADDR`, the address
  of the TCP peer, and the `X-Forwarded-For` header is ignored. The `Client-IP`
  header is never read.
- When `REMOTE_ADDR` is one of the listed proxies, `X-Forwarded-For` is read
  from **right to left**. Each trusted proxy is skipped, and the first address
  that is not trusted is the client. If every entry is trusted, the leftmost one
  is used. As long as the client's own address is **not** in the list, entries it
  added to the left of what your proxy appended are never reached. A client whose
  address is inside a listed range (an internal host in `10.0.0.0/8`, say) is
  skipped like a proxy, and the value it wrote to the left is read instead, so
  list your proxies' addresses, not whole internal networks.
- An entry that is not a valid IP address (`unknown`, a value with a port such
  as `203.0.113.7:4711`, an empty item) breaks the chain: no client address is
  reported (it is `null`), so no `ip()` group matches. Configure the proxy to
  send bare addresses.
- IPv4-mapped IPv6 addresses (`::ffff:192.0.2.10`) are treated as the IPv4
  address in `REMOTE_ADDR`, in the header, in `trusted_proxies` and in the
  `ip()` allow-list. Addresses are compared in canonical form, so
  `2001:DB8::5` in an `ip()` list matches a client `2001:db8::5`.
- An option that is not an array, or an entry that is not an IP address or CIDR
  range, makes the constructor throw [`InvalidArgumentException`](Exceptions.md),
  so a typo does not silently trust nothing, or everything.
- With a PSR-7 server request, `REMOTE_ADDR` is taken from `getServerParams()`
  when it returns any, otherwise from `$_SERVER`; a bag without `REMOTE_ADDR`
  yields no client address (`null`), so no `ip()` group matches. The
  `X-Forwarded-For` header is read from the request itself (`getHeader()`,
  several header lines are joined in order), because the server parameters of some
  runtimes (ReactPHP) carry no `HTTP_*` entries; `HTTP_X_FORWARDED_FOR` is only
  the fallback when the request has no such header. A plain request has no server
  parameters, so `$_SERVER` is used.

List only proxies you operate. A range wider than your real proxies (for example
`0.0.0.0/0`, or a whole internal network) lets a client forge the address again.

> **Upgrading with a proxy on the same machine or network.** If the proxy's own
> address is in an `ip()` list (for example `ip('127.0.0.1', ...)` behind a local
> nginx), do not upgrade without setting `trusted_proxies`: every visitor then
> appears to come from the proxy, and the group opens to everyone.

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
