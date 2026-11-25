# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [4.0.0] - 2026-11-25

### Security

- **Affected versions: every release up to and including 3.0.0** (the tags 1.1.3,
  1.2, 1.2.1, 2.0.0, 2.1.0 and 3.0.0 were checked; older history is not tagged in
  this repository and was not assessed). `ip()` groups trusted the `Client-IP` and
  `X-Forwarded-For` headers (1.x through `getenv()`, 2.0.0 and later through
  `$_SERVER`), which any client can send, so an `ip()` allow-list could be opened by
  forging an address. With `variable_method` on, `_method` was read from
  `$_REQUEST`, so a `GET` request or a query string could be routed as another
  method. Both are fixed by the breaking changes below; see *Upgrading*.

### Added

- `trusted_proxies` Router option (IP addresses and CIDR ranges).
- `RequestContext::normalizeIp()`: the canonical text form of an IP address
  (IPv4-mapped IPv6 collapsed to IPv4, IPv6 lower-case and shortened), used to
  compare client addresses with `ip()` allow-list entries.

### Changed (breaking)

- **The client IP is `REMOTE_ADDR` by default.** `ip()` groups no longer trust
  the `Client-IP` and `X-Forwarded-For` headers, which any client can send. The
  `Client-IP` header is never read. `X-Forwarded-For` is read only when
  `REMOTE_ADDR` is listed in the new `trusted_proxies` option (IP addresses and
  CIDR ranges, IPv4 or IPv6); the chain is then resolved from right to left,
  skipping trusted proxies. An entry that is not an IP address yields no client
  address (`null`), and IPv4-mapped IPv6 addresses are treated as IPv4. An invalid
  `trusted_proxies` entry throws `InvalidArgumentException` on construction.
- **The `_method` override (`variable_method`) works only on a real `POST`
  request and only from the POST parameters** (the parsed body of a PSR-7 server
  request, otherwise `$_POST`). `GET` requests and `_method` in the query string
  are ignored; `$_REQUEST` is no longer read.
- `ip()` allow-list entries and the client address are compared in canonical
  form (IPv4-mapped IPv6 collapsed to IPv4, IPv6 lower-case and shortened), so
  `ip('::ffff:192.0.2.10')` now matches the IPv4 client `192.0.2.10`.
- With a PSR-7 server request, `RequestContext` takes the server parameters from
  `getServerParams()` (when it returns any), `X-Forwarded-For` from the request's
  header lines (falling back to `HTTP_X_FORWARDED_FOR`) and `_method` from its
  parsed body only, instead of `$_SERVER` and `$_POST`.
- `trusted_proxies` that is not an array throws `InvalidArgumentException`.
- `RequestContext`: the `$requestParams` constructor argument now means the POST
  parameters, and a new last argument `$trustedProxies` was added.

#### Upgrading

- Behind a reverse proxy or load balancer, set `trusted_proxies` to its address
  (not a whole internal network) before upgrading; without it `ip()` groups see
  the proxy's address. **If that address is in an `ip()` list (for example a
  proxy on the same machine with `ip('127.0.0.1', ...)`), the group opens to every
  visitor until you set it.** Have the proxy
  append the peer it saw to `X-Forwarded-For` and send bare IP addresses (no
  ports).
- If a client submitted `_method` with a `GET` request or in the query string,
  change it to a `POST` with `_method` in the body.
- `port()` still reads the port from the `Host` header, `domain()` the host
  name; both remain routing selectors, not access controls (documented).

### Fixed

- `HealthRegistry`: a numeric check name (`'1'`, `'42'`) raised a `TypeError`
  when the registry ran; `names()` now returns strings.
- `HealthEndpoints`: a check detail that is not valid UTF-8 produced an empty
  `503` body. Invalid bytes are now replaced with U+FFFD.
- `HealthEndpoints`: the readiness body encodes `checks` as a JSON object also
  when it is empty (`"checks":{}` instead of `"checks":[]`).

### Documentation

- New [Health Checks](docs/Health-Checks.md) and
  [Readiness Linter](docs/Readiness-Linter.md) pages for the probe endpoints and
  the CI gate, linked from the README, Home, the API reference, Caching and the
  sidebar.
- Route Groups state that `ip()`, `domain()` and `port()` are routing selectors,
  not access controls, and warn about a proxy address that is in an `ip()` list.
- Configuration documents `trusted_proxies`, says the `_method` override applies
  only to the POST body of a real `POST` request and is no substitute for a CSRF
  token (with an example), and Health Checks advises limiting the probe routes at
  the proxy or firewall.
- Exceptions lists the new `InvalidArgumentException` cases, and the "Upgrading from
  1.x to 2.0" page now says which releases trusted the client-IP headers.

## [3.0.0] - 2026-11-22

### Changed (breaking)

- The development dependency `initphp/http` moved to `^4.0` (was `^2.0`); it is
  only used by the test suite.
- **Requires PHP `^8.2`.** Support for PHP 8.1 is dropped; CI now runs PHP 8.2, 8.3, 8.4 and 8.5.
- **Coding standard moved to PER-CS** through the shared `.php-cs-fixer.dist.php`; the whole code base was reformatted.

### Fixed

- Removed two stale PHPStan baseline entries (`if.alwaysFalse`) that newer PHPStan releases report as unmatched, which kept the static analysis job on `main` red.

## [2.1.0] - 2026-06-20

### Added

- **Liveness/readiness health endpoints** in a new `InitPHP\Router\Health\`
  namespace. `HealthEndpoints` registers a `/healthz`-style liveness route
  (always 200) and a `/readyz`-style readiness route (200 when all checks pass,
  503 when any fails) on a `Router`, each with a JSON body. `HealthRegistry`
  holds named readiness checks (callables returning `bool` or `CheckResult`);
  a throwing check is recorded as a failure. `HealthReport`/`CheckResult` model
  the aggregated outcome. Routes carry a `health` option tag for discovery.
- **Production-readiness linter** `ReadinessLinter`: inspects the configured
  routes and asserts the app exposes both a liveness and a readiness endpoint,
  and that readiness has at least one registered check. Returns a structured
  `LintReport` of `LintFinding`s (stable `code`, `LintSeverity`, `message`) with
  `passes()` / `exitCode()` so it can gate CI. Recognises both tagged endpoints
  and hand-rolled conventional paths.

## [2.0.0] - 2026-06-09

A major modernisation release. The monolithic router is decomposed into focused,
fully-typed components, several long-standing bugs are fixed, and the public API
is tidied. The route **registration** API (`get()`, `post()`, `group()`,
`name()`, `route()`, `filter()`, …) is unchanged, so most applications upgrade
with little or no code change. See the
[upgrade guide](./docs/upgrading-1.x-to-2.0.md) for the breaking changes and
migration steps.

### Added

- A `Route` value object and a `RouteCollection`, replacing the array-based route
  table.
- Focused collaborators: `Matching\PatternRegistry`, `Matching\RouteMatcher`,
  `Dispatch\Dispatcher`, `Dispatch\MiddlewareRunner`, `Resolver\ControllerResolver`,
  `Resolver\ParameterResolver`, `Link\FileLinkHandler`, `Cache\RouteCache`,
  `Http\RequestContext`.
- `setNotFoundHandler()` to define the 404 handler.
- `getCurrentController()`, `getCurrentControllerMethod()` and
  `getCurrentArguments()` accessors.
- First-class [PSR-11](https://www.php-fig.org/psr/psr-11/) container support.
- Path-traversal protection for directory links.
- A comprehensive test suite (90%+ line coverage), a CI workflow (PHP 8.1–8.4,
  PHPStan at max, PHP-CS-Fixer, and a coverage floor), and full English
  documentation under `docs/`.

### Changed

- **Minimum PHP version is now 8.1.**
- `resolve()` returns a `Route` object (or `null`) instead of an array.
- Middleware returning `null` now halts the pipeline cleanly instead of calling
  `exit()`.
- The client IP is detected from `$_SERVER` instead of `getenv()`.
- Large linked files are streamed through the PSR-7 response instead of
  `readfile()` + `exit()`.
- Internally: private methods renamed to camelCase, all properties/parameters/
  return types declared, PSR-12 applied, and PHPDoc rewritten to match the code.

### Deprecated

- `error_404()` — use `setNotFoundHandler()`. The alias still works.

### Removed

- The `INITPHP_ROUTER_CURRENT_ARGUMENTS`, `INITPHP_ROUTER_CURRENT_CONTROLLER` and
  `INITPHP_ROUTER_CURRENT_METHOD` global constants (use the `getCurrent*()`
  accessors).
- All `exit()` / `die()` calls from library code.

### Fixed

- IP-restricted route groups never matched, even for an allow-listed client.
- Default ports (80/443) were never dropped from generated URIs.
- The router could only be dispatched once per process (unguarded global
  constants raised "Constant already defined" on a second dispatch).
- Directory links were vulnerable to path traversal (e.g. `/assets/../secret`).
- `resolve()` corrupted internal state for `LINK` routes and could fatal when
  called before `dispatch()`.
- The cache-read error referenced the wrong configuration key, and a failed cache
  write could throw from the destructor.
- Controller name resolution used a whitespace `ltrim()` mask instead of a
  backslash mask.
- Parameter resolution silently dropped a required built-in parameter, causing a
  later `ArgumentCountError`.
- Multi-slash path normalisation and the fragile `array_merge_recursive`-based
  options merge.

### Security

- Directory links now verify the resolved real path stays inside the link root,
  preventing path traversal.
- Route-cache `unserialize()` is hardened with `allowed_classes: false`.

[4.0.0]: https://github.com/InitPHP/Router/compare/3.0.0...4.0.0
[3.0.0]: https://github.com/InitPHP/Router/compare/2.1.0...3.0.0
[2.0.0]: https://github.com/InitPHP/Router/compare/1.2.1...2.0.0
