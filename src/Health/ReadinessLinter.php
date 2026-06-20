<?php

/**
 * ReadinessLinter.php
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

use InitPHP\Router\Router;

/**
 * Production-readiness linter: inspects a configured {@see Router} and asserts
 * the application exposes both a liveness *and* a readiness endpoint, and that
 * readiness has at least one registered check.
 *
 * It returns a structured {@see LintReport} (not exceptions), so it can run as a
 * CI gate — fail the build when `passes()` is false, or use `exitCode()` from a
 * console entry point.
 *
 * Endpoints registered via {@see HealthEndpoints} are recognised by their
 * `health` route-option tag regardless of path. As a fallback (for apps that
 * hand-roll the routes) the linter also recognises the conventional
 * `/healthz` / `/readyz` (and `/health` / `/ready`) paths.
 */
final class ReadinessLinter
{
    /**
     * Conventional liveness paths recognised without the `health` option tag.
     *
     * @var list<string>
     */
    private const LIVENESS_PATH_HINTS = ['/healthz', '/health', '/livez', '/liveness'];

    /**
     * Conventional readiness paths recognised without the `health` option tag.
     *
     * @var list<string>
     */
    private const READINESS_PATH_HINTS = ['/readyz', '/ready', '/readiness'];

    public function __construct(private ?HealthRegistry $registry = null)
    {
    }

    /**
     * Inspect $router and produce a readiness report.
     *
     * When a {@see HealthRegistry} was supplied to the linter, it is also used
     * to assert the readiness endpoint has at least one check. (Pass the same
     * registry you handed to {@see HealthEndpoints}.)
     */
    public function lint(Router $router): LintReport
    {
        $routes = $router->getRoutes();

        $hasLiveness = $this->hasKind($routes, HealthEndpoints::KIND_LIVENESS, self::LIVENESS_PATH_HINTS);
        $hasReadiness = $this->hasKind($routes, HealthEndpoints::KIND_READINESS, self::READINESS_PATH_HINTS);

        $findings = [];

        if (!$hasLiveness) {
            $findings[] = new LintFinding(
                LintFinding::MISSING_LIVENESS,
                LintSeverity::ERROR,
                'No liveness endpoint is registered. Expose a "/healthz"-style route '
                . '(e.g. via HealthEndpoints::registerLiveness()) so orchestrators can '
                . 'tell whether the process is alive.'
            );
        }

        if (!$hasReadiness) {
            $findings[] = new LintFinding(
                LintFinding::MISSING_READINESS,
                LintSeverity::ERROR,
                'No readiness endpoint is registered. Expose a "/readyz"-style route '
                . '(e.g. via HealthEndpoints::registerReadiness()) so load balancers '
                . 'only send traffic once dependencies are ready.'
            );
        } elseif ($this->registry !== null && $this->registry->count() < 1) {
            $findings[] = new LintFinding(
                LintFinding::READINESS_HAS_NO_CHECKS,
                LintSeverity::ERROR,
                'The readiness endpoint has no registered checks; it would always '
                . 'report ready. Register at least one dependency check '
                . '(e.g. db-ping, cache-ping) on the HealthRegistry.'
            );
        }

        return new LintReport($findings);
    }

    /**
     * Whether any registered route is of the given health kind — matched first
     * by the explicit `health` route-option tag, then by a conventional path
     * hint as a fallback.
     *
     * @param array<string, mixed> $routes The grouped output of Router::getRoutes().
     * @param string               $kind   HealthEndpoints::KIND_LIVENESS|KIND_READINESS.
     * @param list<string>         $pathHints
     */
    private function hasKind(array $routes, string $kind, array $pathHints): bool
    {
        foreach ($routes as $byPath) {
            if (!\is_array($byPath)) {
                continue;
            }
            foreach ($byPath as $path => $route) {
                $options = (\is_array($route) && \is_array($route['options'] ?? null)) ? $route['options'] : [];
                if (($options[HealthEndpoints::OPTION_KEY] ?? null) === $kind) {
                    return true;
                }
                if ($this->pathMatchesHint((string) $path, $pathHints)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether $path ends with one of the conventional hint paths. The router
     * stores absolute URLs (scheme://host/path), so a suffix match keeps the
     * check host-agnostic while still anchoring on the path segment.
     *
     * @param list<string> $pathHints
     */
    private function pathMatchesHint(string $path, array $pathHints): bool
    {
        foreach ($pathHints as $hint) {
            if ($path === $hint || \str_ends_with($path, $hint)) {
                return true;
            }
        }

        return false;
    }
}
