<?php

declare(strict_types=1);

namespace Tests\Router\Unit\Health;

use InitPHP\HTTP\Message\Request;
use InitPHP\HTTP\Message\Response;
use InitPHP\HTTP\Message\Stream;
use InitPHP\Router\Health\HealthEndpoints;
use InitPHP\Router\Health\HealthRegistry;
use InitPHP\Router\Health\LintFinding;
use InitPHP\Router\Health\LintSeverity;
use InitPHP\Router\Health\ReadinessLinter;
use InitPHP\Router\Router;
use PHPUnit\Framework\TestCase;

/**
 * The production-readiness linter: it must flag a missing liveness or readiness
 * endpoint (and a readiness endpoint with no checks), and pass when both exist
 * with at least one check — so it can gate CI.
 */
final class ReadinessLinterTest extends TestCase
{
    private function router(): Router
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $request = new Request('GET', 'http://localhost/', [], null, '1.1');
        $response = new Response(200, [], new Stream('', null), '1.1');

        return new Router($request, $response);
    }

    /**
     * @param list<LintFinding> $findings
     */
    private function codes(array $findings): array
    {
        return array_map(static fn (LintFinding $f): string => $f->code, $findings);
    }

    public function testPassesWhenBothEndpointsExistWithACheck(): void
    {
        $registry = new HealthRegistry();
        $registry->register('db-ping', static fn (): bool => true);

        $router = $this->router();
        (new HealthEndpoints($registry))->register($router);

        $report = (new ReadinessLinter($registry))->lint($router);

        self::assertTrue($report->passes());
        self::assertFalse($report->hasFindings());
        self::assertSame(0, $report->exitCode());
    }

    public function testFlagsMissingBothEndpoints(): void
    {
        $router = $this->router();
        $router->get('/', static fn (): string => 'home');

        $report = (new ReadinessLinter())->lint($router);

        self::assertFalse($report->passes());
        self::assertSame(1, $report->exitCode());
        self::assertContains(LintFinding::MISSING_LIVENESS, $this->codes($report->findings));
        self::assertContains(LintFinding::MISSING_READINESS, $this->codes($report->findings));
    }

    public function testFlagsMissingLivenessOnly(): void
    {
        $registry = new HealthRegistry();
        $registry->register('db-ping', static fn (): bool => true);

        $router = $this->router();
        (new HealthEndpoints($registry))->registerReadiness($router);

        $report = (new ReadinessLinter($registry))->lint($router);

        self::assertFalse($report->passes());
        $codes = $this->codes($report->findings);
        self::assertContains(LintFinding::MISSING_LIVENESS, $codes);
        self::assertNotContains(LintFinding::MISSING_READINESS, $codes);
    }

    public function testFlagsMissingReadinessOnly(): void
    {
        $router = $this->router();
        (new HealthEndpoints(new HealthRegistry()))->registerLiveness($router);

        $report = (new ReadinessLinter())->lint($router);

        self::assertFalse($report->passes());
        $codes = $this->codes($report->findings);
        self::assertContains(LintFinding::MISSING_READINESS, $codes);
        self::assertNotContains(LintFinding::MISSING_LIVENESS, $codes);
    }

    public function testFlagsReadinessWithoutChecks(): void
    {
        $registry = new HealthRegistry(); // no checks registered
        $router = $this->router();
        (new HealthEndpoints($registry))->register($router);

        $report = (new ReadinessLinter($registry))->lint($router);

        self::assertFalse($report->passes());
        $codes = $this->codes($report->findings);
        self::assertContains(LintFinding::READINESS_HAS_NO_CHECKS, $codes);
        self::assertNotContains(LintFinding::MISSING_LIVENESS, $codes);
        self::assertNotContains(LintFinding::MISSING_READINESS, $codes);
    }

    public function testRecognisesHandRolledConventionalPaths(): void
    {
        // No HealthEndpoints tag, just conventional paths — the linter's path
        // heuristic must still recognise them. No registry => skip the check count.
        $router = $this->router();
        $router->get('/healthz', static fn (): string => 'ok');
        $router->get('/readyz', static fn (): string => 'ready');

        $report = (new ReadinessLinter())->lint($router);

        self::assertTrue($report->passes());
    }

    public function testFindingsAreErrorSeverityAndSerialisable(): void
    {
        $router = $this->router();
        $report = (new ReadinessLinter())->lint($router);

        self::assertNotEmpty($report->errors());
        foreach ($report->findings as $finding) {
            self::assertSame(LintSeverity::ERROR, $finding->severity);
        }

        $array = $report->toArray();
        self::assertFalse($array['passes']);
        self::assertSame('error', $array['findings'][0]['severity']);
        self::assertArrayHasKey('code', $array['findings'][0]);
        self::assertArrayHasKey('message', $array['findings'][0]);
    }

    public function testLinterWithoutRegistryDoesNotFlagCheckCount(): void
    {
        // When no registry is supplied, the linter cannot know the check count,
        // so it must NOT emit READINESS_HAS_NO_CHECKS (avoids false positives).
        $router = $this->router();
        (new HealthEndpoints(new HealthRegistry()))->register($router);

        $report = (new ReadinessLinter())->lint($router);

        self::assertTrue($report->passes());
        self::assertNotContains(LintFinding::READINESS_HAS_NO_CHECKS, $this->codes($report->findings));
    }
}
