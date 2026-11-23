# Health Checks

`InitPHP\Router\Health` adds two probe endpoints to a router, the way
orchestrators and load balancers expect them:

| Probe | Default path | Question it answers | Runs checks? |
| --- | --- | --- | --- |
| **Liveness** | `/healthz` | Is the process up and able to serve? | No |
| **Readiness** | `/readyz` | Are the dependencies OK, so traffic can be routed here? | Yes |

Use liveness for "restart me if this fails" probes and readiness for "send me
traffic only when this passes" probes. To verify in CI that an application
exposes both, see the [Readiness Linter](Readiness-Linter.md).

## Quick example

```php
use InitPHP\Router\Health\{CheckResult, HealthEndpoints, HealthRegistry};

$registry = new HealthRegistry();

// A check may return a bool...
$registry->register('db-ping', fn (): bool => $pdo->query('SELECT 1') !== false);

// ...or a CheckResult with an optional detail.
$registry->register('cache-ping', function () use ($redis): CheckResult {
    return $redis->ping()
        ? CheckResult::ok('cache-ping')
        : CheckResult::fail('cache-ping', 'no PONG');
});

(new HealthEndpoints($registry))->register($router); // adds /healthz and /readyz
```

## `HealthEndpoints`

`final class HealthEndpoints` registers the probe routes on a
[`Router`](API-Reference.md).

```php
new HealthEndpoints(HealthRegistry $registry)
```

| Method | Returns | Description |
| --- | --- | --- |
| `register(Router $router, string $livenessPath = '/healthz', string $readinessPath = '/readyz')` | `$this` | Registers both routes. |
| `registerLiveness(Router $router, string $path = '/healthz')` | `$this` | Registers only the liveness route. |
| `registerReadiness(Router $router, string $path = '/readyz')` | `$this` | Registers only the readiness route. |
| `registry()` | `HealthRegistry` | The registry behind the readiness route. |

The defaults are also available as the constants `DEFAULT_LIVENESS_PATH` and
`DEFAULT_READINESS_PATH`. Paths go through the same prefix and base-path rules as
any other route, so a route group or a `base_path` option applies to them. See
[Route Groups](Route-Groups.md) and [Configuration](Configuration.md).

```php
(new HealthEndpoints($registry))->register($router, '/_alive', '/_ready');
```

Both routes answer `GET` and `HEAD`, so probes that issue `HEAD` work too.

### Responses

Both endpoints reply with `Content-Type: application/json; charset=utf-8` and
`Cache-Control: no-store`.

**Liveness** runs no checks and always returns `200`:

```json
{"status":"ok"}
```

**Readiness** runs the registry and returns `200` when every check passes or
`503` (Service Unavailable) when any check fails. The body lists each check by
its registered name:

```json
{
    "status": "fail",
    "checks": {
        "db-ping": {"status": "ok"},
        "cache-ping": {"status": "fail", "detail": "no PONG"}
    }
}
```

(Shown formatted for readability; the endpoint emits compact JSON.) `detail`
appears only when the check supplied one.

> The response is built through the PSR-7 interfaces only. If the response body
> stream is not writable, the status and headers are still set but the body stays
> empty.

### Route tags

Each probe route carries a `health` route option: `'liveness'` or `'readiness'`
(constants `HealthEndpoints::OPTION_KEY`, `KIND_LIVENESS` and `KIND_READINESS`).
The [Readiness Linter](Readiness-Linter.md) uses the tag to recognise the probes
whatever paths you chose.

### Closures and route caching

The probe routes are registered with closure handlers. Closures cannot be
serialized, so do not combine them with the [route cache](Caching.md#limitations).

## `HealthRegistry`

`final class HealthRegistry` holds named readiness checks. It has no constructor
arguments.

| Method | Returns | Description |
| --- | --- | --- |
| `register(string $name, callable $check)` | `$this` | Adds a check, or replaces the one with the same name. |
| `has(string $name)` | `bool` | Whether a check with that name is registered. |
| `remove(string $name)` | `$this` | Removes a check. Unknown names are ignored. |
| `count()` | `int` | Number of registered checks. |
| `names()` | `list<string>` | Check names, in registration order. |
| `run()` | `HealthReport` | Runs every check and aggregates the outcomes. |

A check is a callable with the signature `callable(): bool|CheckResult`:

- Returning `true` is a pass. Any other bool, or any value that is neither
  `true` nor a `CheckResult`, is a failure.
- Returning a `CheckResult` keeps its `ok` flag and `detail`, but the result is
  reported under the **registered name**, whatever name the `CheckResult` itself
  carries.
- Throwing is recorded as a failure with the exception message as the `detail`.
  A misbehaving dependency probe never takes the endpoint down.

A registry with no checks is healthy, so the readiness route would always report
`200`. The [Readiness Linter](Readiness-Linter.md) can flag this.

Checks run one after another, in registration order, on the request that hits
the endpoint. Keep them fast, and give any network call a short timeout of its
own; the registry does not add one.

## `CheckResult`

`final class CheckResult` is an immutable value object: the outcome of one check.

```php
new CheckResult(string $name, bool $ok, ?string $detail = null)
```

| Member | Description |
| --- | --- |
| `$name`, `$ok`, `$detail` | `public readonly` properties. |
| `CheckResult::ok(string $name, ?string $detail = null)` | A passing result. |
| `CheckResult::fail(string $name, ?string $detail = null)` | A failing result. |
| `toArray()` | `['status' => 'ok'\|'fail']`, plus `'detail'` when it is not `null`. |

## `HealthReport`

`final class HealthReport` is what `HealthRegistry::run()` returns. You can use
it directly, for example from a CLI command or a custom endpoint.

| Member | Description |
| --- | --- |
| `bool $healthy` | `public readonly`; `true` only when every check passed. |
| `list<CheckResult> $results` | `public readonly`; outcomes in registration order. |
| `statusCode()` | `200` when healthy, `503` otherwise. |
| `toArray()` | `['status' => 'ok'\|'fail', 'checks' => [name => CheckResult::toArray()]]`, the readiness response body. |

```php
$report = $registry->run();

if (!$report->healthy) {
    error_log(json_encode($report->toArray(), JSON_UNESCAPED_SLASHES));
}
```

Next: [Readiness Linter](Readiness-Linter.md).
