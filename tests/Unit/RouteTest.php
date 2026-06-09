<?php

declare(strict_types=1);

namespace Tests\Router\Unit;

use InitPHP\Router\Route;
use PHPUnit\Framework\TestCase;

final class RouteTest extends TestCase
{
    public function testExposesItsData(): void
    {
        $route = new Route(3, ['GET', 'POST'], 'http://localhost/x', ['Home', 'index'], ['prefix' => '/x']);

        $this->assertSame(3, $route->getId());
        $this->assertSame(['GET', 'POST'], $route->getMethods());
        $this->assertSame('http://localhost/x', $route->getPath());
        $this->assertSame(['Home', 'index'], $route->getHandler());
        $this->assertSame(['prefix' => '/x'], $route->getOptions());
        $this->assertSame('/x', $route->getOption('prefix'));
        $this->assertSame('fallback', $route->getOption('missing', 'fallback'));
        $this->assertSame([], $route->getArguments());
    }

    public function testIsLinkOnlyForLinkOnlyMethods(): void
    {
        $this->assertTrue((new Route(1, ['LINK'], '/p', 'src'))->isLink());
        $this->assertFalse((new Route(1, ['GET', 'LINK'], '/p', 'src'))->isLink());
    }

    public function testAddMiddlewareNestsByBucket(): void
    {
        $route = new Route(1, ['GET'], '/p', 'h');
        $route->addMiddleware('before', 'B1');
        $route->addMiddleware('after', 'A1');
        $route->addMiddleware('both', 'M1');

        $this->assertSame(
            ['before' => ['B1'], 'after' => ['A1'], 0 => 'M1'],
            $route->getOptions()['middleware']
        );
    }

    public function testWithArgumentsReturnsACopyAndLeavesOriginalUntouched(): void
    {
        $route = new Route(1, ['GET'], '/user/{id}', 'h');
        $matched = $route->withArguments(['42']);

        $this->assertSame(['42'], $matched->getArguments());
        $this->assertSame([], $route->getArguments());
        $this->assertNotSame($route, $matched);
    }

    public function testArrayRoundTrip(): void
    {
        $route = new Route(7, ['PUT', 'PATCH'], 'http://localhost/u/{id}', 'Ctrl@update', ['name' => 'u']);
        $restored = Route::fromArray($route->toArray());

        $this->assertSame($route->toArray(), $restored->toArray());
        $this->assertSame(7, $restored->getId());
    }
}
