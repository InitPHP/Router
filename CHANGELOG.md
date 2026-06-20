# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/InitPHP/Router/compare/2.0.0...HEAD
[2.0.0]: https://github.com/InitPHP/Router/compare/1.2.1...2.0.0
