<?php

declare(strict_types=1);

namespace Tests\Router\Unit\Health;

use InitPHP\HTTP\Message\Request;
use InitPHP\HTTP\Message\Response;
use InitPHP\HTTP\Message\Stream;
use InitPHP\Router\Health\CheckResult;
use InitPHP\Router\Health\HealthEndpoints;
use InitPHP\Router\Health\HealthRegistry;
use InitPHP\Router\Router;
use PHPUnit\Framework\TestCase;

/**
 * Liveness/readiness endpoints wired onto a real Router and dispatched
 * end-to-end through the PSR-7 pipeline. Liveness must always be 200; readiness
 * must aggregate the registry into 200 (all pass) or 503 (any fail) with a JSON
 * body listing each check.
 */
final class HealthEndpointsTest extends TestCase
{
    private function router(string $url, string $method = 'GET'): Router
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $request = new Request($method, $url, [], null, '1.1');
        $response = new Response(200, [], new Stream('', null), '1.1');

        return new Router($request, $response);
    }

    public function testLivenessReturns200WithOkBody(): void
    {
        $endpoints = new HealthEndpoints(new HealthRegistry());
        $router = $this->router('http://localhost/healthz');
        $endpoints->register($router);

        $response = $router->dispatch();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            'application/json; charset=utf-8',
            $response->getHeaderLine('Content-Type')
        );
        $body = json_decode((string) $response->getBody(), true);
        self::assertSame('ok', $body['status']);
    }

    public function testReadinessReturns200WhenAllChecksPass(): void
    {
        $registry = new HealthRegistry();
        $registry->register('db-ping', static fn (): bool => true);
        $registry->register('cache-ping', static fn (): CheckResult => CheckResult::ok('cache-ping', 'pong'));

        $endpoints = new HealthEndpoints($registry);
        $router = $this->router('http://localhost/readyz');
        $endpoints->register($router);

        $response = $router->dispatch();

        self::assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        self::assertSame('ok', $body['status']);
        self::assertSame('ok', $body['checks']['db-ping']['status']);
        self::assertSame('ok', $body['checks']['cache-ping']['status']);
        self::assertSame('pong', $body['checks']['cache-ping']['detail']);
    }

    public function testReadinessReturns503WhenAnyCheckFails(): void
    {
        $registry = new HealthRegistry();
        $registry->register('db-ping', static fn (): bool => true);
        $registry->register('cache-ping', static fn (): CheckResult => CheckResult::fail('cache-ping', 'connection refused'));

        $endpoints = new HealthEndpoints($registry);
        $router = $this->router('http://localhost/readyz');
        $endpoints->register($router);

        $response = $router->dispatch();

        self::assertSame(503, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        self::assertSame('fail', $body['status']);
        self::assertSame('ok', $body['checks']['db-ping']['status']);
        self::assertSame('fail', $body['checks']['cache-ping']['status']);
        self::assertSame('connection refused', $body['checks']['cache-ping']['detail']);
    }

    public function testReadinessTreatsThrowingCheckAsFailure(): void
    {
        $registry = new HealthRegistry();
        $registry->register('db-ping', static function (): bool {
            throw new \RuntimeException('boom');
        });

        $endpoints = new HealthEndpoints($registry);
        $router = $this->router('http://localhost/readyz');
        $endpoints->register($router);

        $response = $router->dispatch();

        self::assertSame(503, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        self::assertSame('fail', $body['checks']['db-ping']['status']);
        self::assertSame('boom', $body['checks']['db-ping']['detail']);
    }

    public function testReadinessWithNoChecksIsVacuously200(): void
    {
        $endpoints = new HealthEndpoints(new HealthRegistry());
        $router = $this->router('http://localhost/readyz');
        $endpoints->register($router);

        $response = $router->dispatch();

        self::assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        self::assertSame('ok', $body['status']);
        self::assertSame([], $body['checks']);
    }

    public function testCustomPathsAreHonoured(): void
    {
        $endpoints = new HealthEndpoints(new HealthRegistry());
        $router = $this->router('http://localhost/_alive');
        $endpoints->register($router, '/_alive', '/_ready');

        $response = $router->dispatch();
        self::assertSame(200, $response->getStatusCode());

        $routes = $router->getRoutes('GET');
        self::assertArrayHasKey('http://localhost/_alive', $routes);
        self::assertArrayHasKey('http://localhost/_ready', $routes);
    }

    public function testRegistryAccessorReturnsBackingRegistry(): void
    {
        $registry = new HealthRegistry();
        $endpoints = new HealthEndpoints($registry);

        self::assertSame($registry, $endpoints->registry());
    }

    public function testRoutesAreTaggedWithHealthOption(): void
    {
        $endpoints = new HealthEndpoints(new HealthRegistry());
        $router = $this->router('http://localhost/');
        $endpoints->register($router);

        $routes = $router->getRoutes('GET');
        self::assertSame(
            HealthEndpoints::KIND_LIVENESS,
            $routes['http://localhost/healthz']['options'][HealthEndpoints::OPTION_KEY]
        );
        self::assertSame(
            HealthEndpoints::KIND_READINESS,
            $routes['http://localhost/readyz']['options'][HealthEndpoints::OPTION_KEY]
        );
    }
}
