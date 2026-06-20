<?php

/**
 * HealthRegistry.php
 *
 * This file is part of Router.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Router\Health;

use Throwable;

/**
 * A registry of named readiness checks (e.g. "db-ping", "cache-ping").
 *
 * Each check is a callable returning either a bool (true = ok) or a
 * {@see CheckResult}; a check that throws is treated as a failure, with the
 * exception message captured as the detail — a misbehaving dependency probe must
 * never take down the readiness endpoint itself. Running the registry produces a
 * {@see HealthReport} aggregating every result.
 *
 * Liveness ("is the process up?") deliberately has *no* checks: reaching the
 * handler already proves the process is alive. Readiness ("are dependencies
 * OK?") is what this registry feeds.
 */
final class HealthRegistry
{
    /**
     * @var array<string, callable(): (bool|CheckResult)>
     */
    private array $checks = [];

    /**
     * Register (or replace) a named check. The callable returns true/false or a
     * {@see CheckResult}; throwing is allowed and is recorded as a failure.
     *
     * @param string                              $name
     * @param callable(): (bool|CheckResult)      $check
     * @return $this
     */
    public function register(string $name, callable $check): self
    {
        $this->checks[$name] = $check;

        return $this;
    }

    /**
     * Whether a check with the given name is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->checks[$name]);
    }

    /**
     * Remove a registered check. No-op when the name is unknown.
     *
     * @return $this
     */
    public function remove(string $name): self
    {
        unset($this->checks[$name]);

        return $this;
    }

    /**
     * The number of registered checks.
     */
    public function count(): int
    {
        return \count($this->checks);
    }

    /**
     * The registered check names, in registration order.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return \array_keys($this->checks);
    }

    /**
     * Run every registered check and aggregate the outcomes into a
     * {@see HealthReport}. The report is healthy only when all checks pass; a
     * registry with no checks is vacuously healthy.
     */
    public function run(): HealthReport
    {
        $results = [];
        $healthy = true;
        foreach ($this->checks as $name => $check) {
            $result = $this->runOne($name, $check);
            $results[] = $result;
            if (!$result->ok) {
                $healthy = false;
            }
        }

        return new HealthReport($healthy, $results);
    }

    /**
     * Execute a single check, normalising its return value (bool or
     * CheckResult) and converting any thrown exception into a failed result.
     *
     * @param callable(): (bool|CheckResult) $check
     */
    private function runOne(string $name, callable $check): CheckResult
    {
        try {
            $outcome = $check();
        } catch (Throwable $e) {
            return CheckResult::fail($name, $e->getMessage());
        }

        if ($outcome instanceof CheckResult) {
            // Preserve the check's own detail but pin the registry name so the
            // report key always matches the registration name.
            return new CheckResult($name, $outcome->ok, $outcome->detail);
        }

        return new CheckResult($name, $outcome === true);
    }
}
