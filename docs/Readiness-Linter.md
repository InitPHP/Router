# Readiness Linter

`ReadinessLinter` inspects a configured router and checks that the application
is production-ready in one narrow sense: it exposes both a liveness and a
readiness endpoint, and the readiness side has at least one check. It returns a
structured report instead of throwing, so it fits a CI gate. The endpoints
themselves are described in [Health Checks](Health-Checks.md).

```php
use InitPHP\Router\Health\ReadinessLinter;

$report = (new ReadinessLinter($registry))->lint($router);

if (!$report->passes()) {
    foreach ($report->findings as $finding) {
        fwrite(STDERR, "[{$finding->severity->value}] {$finding->code}: {$finding->message}\n");
    }
    exit($report->exitCode()); // 1 on failure, 0 on pass
}
```

## `ReadinessLinter`

```php
new ReadinessLinter(?HealthRegistry $registry = null)
```

| Method | Returns | Description |
| --- | --- | --- |
| `lint(Router $router)` | `LintReport` | Inspects the router's registered routes. |

Pass the **same** `HealthRegistry` you gave to `HealthEndpoints`. Without a
registry the linter cannot count checks, and it skips the "no checks" rule.

### What it looks for

A probe counts as present when a registered route either

1. carries the `health` route option with the matching kind, which is how
   `HealthEndpoints` tags its routes, so custom paths are fine; or
2. has a path that ends with one of these conventional paths, which covers
   hand-written routes:

| Kind | Recognised path endings |
| --- | --- |
| Liveness | `/healthz`, `/health`, `/livez`, `/liveness` |
| Readiness | `/readyz`, `/ready`, `/readiness` |

The path match is a suffix match, so it works whatever scheme, host or prefix
the route was registered under.

The linter reads routes through `Router::getRoutes()`. Call it after your routes
are registered.

### Findings

| Code | Constant on `LintFinding` | Severity | Raised when |
| --- | --- | --- | --- |
| `missing_liveness` | `MISSING_LIVENESS` | error | No liveness endpoint is registered. |
| `missing_readiness` | `MISSING_READINESS` | error | No readiness endpoint is registered. |
| `readiness_has_no_checks` | `READINESS_HAS_NO_CHECKS` | error | A readiness endpoint exists, a registry was passed to the linter, and the registry has no checks. |

All three are currently error-severity. `LintSeverity` also defines `WARNING`,
which is advisory and does not fail the gate; the linter itself does not emit
warnings today.

## `LintReport`

`final class LintReport`, built from `list<LintFinding> $findings` (a
`public readonly` property, in the order the rules ran).

| Method | Returns | Description |
| --- | --- | --- |
| `passes()` | `bool` | `true` when there is no error-severity finding. |
| `hasFindings()` | `bool` | `true` when there is any finding at all. |
| `errors()` | `list<LintFinding>` | Only the error-severity findings. |
| `exitCode()` | `int` | `0` when the gate passes, `1` otherwise. |
| `toArray()` | `array` | `['passes' => bool, 'findings' => list<array>]`. |

## `LintFinding` and `LintSeverity`

`final class LintFinding` has three `public readonly` properties: `string $code`,
`LintSeverity $severity` and `string $message`. The `code` is stable, so it is
the value to match on or allow-list in CI. `toArray()` returns
`['code' => string, 'severity' => string, 'message' => string]`.

`enum LintSeverity: string` has the cases `ERROR` (`'error'`) and `WARNING`
(`'warning'`).

## Using it in a test

Because the linter works on a built router, a unit test is the simplest gate:

```php
use InitPHP\Router\Health\ReadinessLinter;
use PHPUnit\Framework\TestCase;

final class ReadinessTest extends TestCase
{
    public function testAppExposesHealthEndpoints(): void
    {
        [$router, $registry] = buildApplication(); // your own bootstrap

        $report = (new ReadinessLinter($registry))->lint($router);

        $this->assertTrue($report->passes(), json_encode($report->toArray()));
    }
}
```

Next: [Health Checks](Health-Checks.md) · [API Reference](API-Reference.md).
