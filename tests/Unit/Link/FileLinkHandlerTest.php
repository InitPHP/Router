<?php

declare(strict_types=1);

namespace Tests\Router\Unit\Link;

use InitPHP\HTTP\Message\Response;
use InitPHP\HTTP\Message\Stream;
use InitPHP\Router\Link\FileLinkHandler;
use PHPUnit\Framework\TestCase;

final class FileLinkHandlerTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/router-link-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/public', 0777, true);
        file_put_contents($this->root . '/public/app.css', 'body{color:red}');
        file_put_contents($this->root . '/secret.txt', 'TOPSECRET');
    }

    protected function tearDown(): void
    {
        @unlink($this->root . '/public/app.css');
        @unlink($this->root . '/secret.txt');
        @rmdir($this->root . '/public');
        @rmdir($this->root);
    }

    private function response(): Response
    {
        return new Response(200, [], new Stream('', null), '1.1');
    }

    public function testResolvesAFileInsideADirectoryLink(): void
    {
        $resolved = (new FileLinkHandler())->resolve($this->root . '/public', ['app.css']);

        $this->assertSame(realpath($this->root . '/public/app.css'), $resolved);
    }

    public function testRejectsTraversalOutsideTheRoot(): void
    {
        $this->assertNull((new FileLinkHandler())->resolve($this->root . '/public/', ['../secret.txt']));
    }

    public function testReturnsNullForMissingFile(): void
    {
        $this->assertNull((new FileLinkHandler())->resolve($this->root . '/public', ['nope.css']));
    }

    public function testResolvesAFileLinkDirectly(): void
    {
        $file = $this->root . '/secret.txt';

        $this->assertSame($file, (new FileLinkHandler())->resolve($file, []));
    }

    public function testServeWritesBodyAndContentHeaders(): void
    {
        $served = (new FileLinkHandler())->serve($this->root . '/public', ['app.css'], $this->response());

        $this->assertNotNull($served);
        $this->assertSame('body{color:red}', (string) $served->getBody());
        $this->assertSame('15', $served->getHeaderLine('Content-Length'));
        $this->assertNotSame('', $served->getHeaderLine('Content-Type'));
    }

    public function testServeStreamsFilesLargerThanTheOldThresholdWithoutExiting(): void
    {
        // 3 MiB — larger than the old 2 MiB readfile()+exit() branch. The whole
        // file must flow through the PSR-7 response (B3 regression).
        $big = $this->root . '/big.bin';
        file_put_contents($big, str_repeat('A', 3 * 1024 * 1024));

        $served = (new FileLinkHandler())->serve($big, [], $this->response());

        $this->assertNotNull($served);
        $this->assertSame(3 * 1024 * 1024, $served->getBody()->getSize());
        $this->assertSame((string) (3 * 1024 * 1024), $served->getHeaderLine('Content-Length'));

        @unlink($big);
    }

    public function testServeReturnsNullForTraversal(): void
    {
        $served = (new FileLinkHandler())->serve($this->root . '/public/', ['../secret.txt'], $this->response());

        $this->assertNull($served);
    }
}
