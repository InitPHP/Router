<?php

declare(strict_types=1);

namespace Tests\Router\Unit\Cache;

use InitPHP\Router\Cache\RouteCache;
use PHPUnit\Framework\TestCase;

final class RouteCacheTest extends TestCase
{
    private string $path = '';

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/router-cache-' . bin2hex(random_bytes(6)) . '.cache';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    public function testDisabledCacheLoadsNullAndDoesNotSave(): void
    {
        $cache = new RouteCache(['enable' => false, 'path' => $this->path]);

        $this->assertFalse($cache->isEnabled());
        $this->assertNull($cache->load());
        $this->assertFalse($cache->save(['id' => 1]));
        $this->assertFileDoesNotExist($this->path);
    }

    public function testEnabledWithoutPathIsNotEnabled(): void
    {
        $this->assertFalse((new RouteCache(['enable' => true]))->isEnabled());
    }

    public function testSaveThenLoadRoundTripsState(): void
    {
        $cache = new RouteCache(['enable' => true, 'path' => $this->path, 'ttl' => 3600]);
        $state = ['id' => 2, 'routes' => ['a' => 1], 'names' => [], 'methodIds' => [], 'error_404' => []];

        $this->assertTrue($cache->save($state));
        $this->assertFileExists($this->path);
        $this->assertSame($state, $cache->load());
    }

    public function testExpiredEntryLoadsNull(): void
    {
        file_put_contents($this->path, serialize([
            'created_at' => time() - 100,
            'data'       => ['id' => 9],
        ]));

        $cache = new RouteCache(['enable' => true, 'path' => $this->path, 'ttl' => 10]);

        $this->assertNull($cache->load());
    }

    public function testCorruptCacheFileLoadsNull(): void
    {
        file_put_contents($this->path, 'this-is-not-serialized-data');

        $cache = new RouteCache(['enable' => true, 'path' => $this->path, 'ttl' => 3600]);

        $this->assertNull($cache->load());
    }
}
