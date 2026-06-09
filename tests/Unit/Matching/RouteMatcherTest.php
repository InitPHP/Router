<?php

declare(strict_types=1);

namespace Tests\Router\Unit\Matching;

use InitPHP\Router\Link\FileLinkHandler;
use InitPHP\Router\Matching\PatternRegistry;
use InitPHP\Router\Matching\RouteMatcher;
use InitPHP\Router\Route;
use InitPHP\Router\RouteCollection;
use PHPUnit\Framework\TestCase;

final class RouteMatcherTest extends TestCase
{
    private const METHODS = ['GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'PATCH', 'OPTIONS', 'ANY', 'LINK'];

    private function matcher(RouteCollection $collection): RouteMatcher
    {
        return new RouteMatcher($collection, new PatternRegistry(), new FileLinkHandler());
    }

    private function collectionWith(Route ...$routes): RouteCollection
    {
        $collection = new RouteCollection(self::METHODS);
        foreach ($routes as $route) {
            $collection->add($route);
        }

        return $collection;
    }

    public function testMatchesAStaticRoute(): void
    {
        $collection = $this->collectionWith(new Route(1, ['GET'], 'http://localhost/about', 'h'));

        $matched = $this->matcher($collection)->match('GET', 'http://localhost/about', null);

        $this->assertNotNull($matched);
        $this->assertSame(1, $matched->getId());
        $this->assertSame([], $matched->getArguments());
    }

    public function testMatchesAPatternRouteAndCapturesArguments(): void
    {
        $collection = $this->collectionWith(new Route(1, ['GET'], 'http://localhost/user/{string}/{id}', 'h'));

        $matched = $this->matcher($collection)->match('GET', 'http://localhost/user/jane/12', null);

        $this->assertNotNull($matched);
        $this->assertSame(['jane', '12'], $matched->getArguments());
    }

    public function testReturnsNullWhenNothingMatches(): void
    {
        $collection = $this->collectionWith(new Route(1, ['GET'], 'http://localhost/', 'h'));

        $this->assertNull($this->matcher($collection)->match('GET', 'http://localhost/nope', null));
    }

    public function testIpAllowListIsEnforced(): void
    {
        $collection = $this->collectionWith(
            new Route(1, ['GET'], 'http://localhost/secret', 'h', ['ip' => ['10.0.0.1']])
        );

        $this->assertNotNull($this->matcher($collection)->match('GET', 'http://localhost/secret', '10.0.0.1'));
        $this->assertNull($this->matcher($collection)->match('GET', 'http://localhost/secret', '10.0.0.2'));
    }

    public function testScoringPrefersTheRouteThatCapturesMoreArguments(): void
    {
        // The specificity heuristic is strlen(path) + capturedArguments * 25, so
        // among routes matching the same URL the one with more captured
        // arguments outranks a static one. (Characterising existing behaviour.)
        $collection = $this->collectionWith(
            new Route(1, ['GET'], 'http://localhost/{any}', 'dynamic'),
            new Route(2, ['GET'], 'http://localhost/contact', 'static')
        );

        $matched = $this->matcher($collection)->match('GET', 'http://localhost/contact', null);

        $this->assertNotNull($matched);
        $this->assertSame('dynamic', $matched->getHandler());
    }

    public function testAnyAndLinkRoutesAreMatchedAcrossMethods(): void
    {
        $collection = $this->collectionWith(new Route(1, ['ANY'], 'http://localhost/health', 'h'));

        $this->assertNotNull($this->matcher($collection)->match('POST', 'http://localhost/health', null));
    }
}
