<?php

declare(strict_types=1);

namespace Tests\Router\Unit\Health;

use InitPHP\Router\Health\CheckResult;
use InitPHP\Router\Health\HealthRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage for the readiness-check registry: registration semantics,
 * bool/CheckResult normalisation, exception-as-failure, and report aggregation.
 */
final class HealthRegistryTest extends TestCase
{
    public function testEmptyRegistryIsVacuouslyHealthy(): void
    {
        $report = (new HealthRegistry())->run();

        self::assertTrue($report->healthy);
        self::assertSame(200, $report->statusCode());
        self::assertSame([], $report->results);
    }

    public function testRegistrationBookkeeping(): void
    {
        $registry = new HealthRegistry();
        $registry->register('db', static fn (): bool => true)
            ->register('cache', static fn (): bool => true);

        self::assertSame(2, $registry->count());
        self::assertTrue($registry->has('db'));
        self::assertSame(['db', 'cache'], $registry->names());

        $registry->remove('db');
        self::assertFalse($registry->has('db'));
        self::assertSame(1, $registry->count());
    }

    public function testRegisteringSameNameReplaces(): void
    {
        $registry = new HealthRegistry();
        $registry->register('db', static fn (): bool => true);
        $registry->register('db', static fn (): bool => false);

        self::assertSame(1, $registry->count());
        self::assertFalse($registry->run()->healthy);
    }

    public function testBoolTrueAndFalseAreNormalised(): void
    {
        $registry = new HealthRegistry();
        $registry->register('up', static fn (): bool => true);
        $registry->register('down', static fn (): bool => false);

        $report = $registry->run();

        self::assertFalse($report->healthy);
        self::assertSame(503, $report->statusCode());
        $map = [];
        foreach ($report->results as $result) {
            $map[$result->name] = $result->ok;
        }
        self::assertTrue($map['up']);
        self::assertFalse($map['down']);
    }

    public function testCheckResultNameIsPinnedToRegistrationName(): void
    {
        $registry = new HealthRegistry();
        // The check returns a CheckResult with a *different* name; the registry
        // must pin the report key to the registration name to keep them aligned.
        $registry->register('db-ping', static fn (): CheckResult => CheckResult::ok('something-else', 'detail'));

        $report = $registry->run();

        self::assertSame('db-ping', $report->results[0]->name);
        self::assertSame('detail', $report->results[0]->detail);
    }

    public function testThrowingCheckBecomesFailureWithMessage(): void
    {
        $registry = new HealthRegistry();
        $registry->register('db', static function (): bool {
            throw new \RuntimeException('cannot connect');
        });

        $report = $registry->run();

        self::assertFalse($report->healthy);
        self::assertFalse($report->results[0]->ok);
        self::assertSame('cannot connect', $report->results[0]->detail);
    }

    public function testReportToArrayShape(): void
    {
        $registry = new HealthRegistry();
        $registry->register('db', static fn (): CheckResult => CheckResult::ok('db'));
        $registry->register('cache', static fn (): CheckResult => CheckResult::fail('cache', 'timeout'));

        $array = $registry->run()->toArray();

        self::assertSame('fail', $array['status']);
        self::assertSame(['status' => 'ok'], $array['checks']['db']);
        self::assertSame(['status' => 'fail', 'detail' => 'timeout'], $array['checks']['cache']);
    }
}
