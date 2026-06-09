<?php

declare(strict_types=1);

namespace Tests\Router\Unit;

use InitPHP\Router\Route;
use InitPHP\Router\RouteCollection;
use PHPUnit\Framework\TestCase;

final class RouteCollectionTest extends TestCase
{
    private const METHODS = ['GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'PATCH', 'OPTIONS', 'ANY', 'LINK'];

    private function collection(): RouteCollection
    {
        return new RouteCollection(self::METHODS);
    }

    public function testNextIdIncrementsAndLastTracksTheNewestRoute(): void
    {
        $collection = $this->collection();

        $first = new Route($collection->nextId(), ['GET'], '/a', 'h');
        $collection->add($first);
        $second = new Route($collection->nextId(), ['GET'], '/b', 'h');
        $collection->add($second);

        $this->assertSame(2, $second->getId());
        $this->assertSame($second, $collection->last());
        $this->assertSame($first, $collection->get(1));
    }

    public function testCandidatesIncludeAnyAndLinkButOfMethodDoesNot(): void
    {
        $collection = $this->collection();
        $collection->add(new Route($collection->nextId(), ['GET'], '/g', 'h'));
        $collection->add(new Route($collection->nextId(), ['ANY'], '/any', 'h'));
        $collection->add(new Route($collection->nextId(), ['LINK'], '/link', 'src'));
        $collection->add(new Route($collection->nextId(), ['POST'], '/p', 'h'));

        $candidatePaths = array_map(static fn (Route $r): string => $r->getPath(), $collection->candidates('GET'));
        sort($candidatePaths);
        $this->assertSame(['/any', '/g', '/link'], $candidatePaths);

        $ofGet = array_map(static fn (Route $r): string => $r->getPath(), $collection->ofMethod('GET'));
        $this->assertSame(['/g'], $ofGet);
    }

    public function testNameIndex(): void
    {
        $collection = $this->collection();
        $route = new Route($collection->nextId(), ['GET'], '/home', 'h');
        $collection->add($route);
        $collection->setName('home', $route->getId());

        $this->assertTrue($collection->hasName('home'));
        $this->assertSame($route, $collection->byName('home'));
        $this->assertNull($collection->byName('missing'));
    }

    public function testClearResetsEverything(): void
    {
        $collection = $this->collection();
        $collection->add(new Route($collection->nextId(), ['GET'], '/g', 'h'));
        $collection->setName('g', 1);

        $collection->clear();

        $this->assertSame([], $collection->all());
        $this->assertFalse($collection->hasName('g'));
        $this->assertSame(1, $collection->nextId());
    }

    public function testCacheRoundTrip(): void
    {
        $collection = $this->collection();
        $route = new Route($collection->nextId(), ['GET'], '/u/{id}', 'Ctrl@show', ['x' => 1]);
        $collection->add($route);
        $collection->setName('u.show', $route->getId());

        $restored = $this->collection();
        $restored->fromCache($collection->toCache());

        $this->assertSame($route->toArray(), $restored->get(1)?->toArray());
        $this->assertSame($route->toArray(), $restored->byName('u.show')?->toArray());
        $this->assertSame(2, $restored->nextId());
    }
}
