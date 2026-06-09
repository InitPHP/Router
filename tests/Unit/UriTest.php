<?php

declare(strict_types=1);

namespace Tests\Router\Unit;

use InitPHP\Router\Uri;
use PHPUnit\Framework\TestCase;

final class UriTest extends TestCase
{
    public function testParsesAndRebuildsAFullUrl(): void
    {
        $uri = new Uri('https://example.com:8443/a/b');

        $this->assertSame('https://example.com:8443/a/b', $uri->__toString());
    }

    public function testDefaultsSchemeToHttpAndRootPath(): void
    {
        $uri = new Uri('//example.com');

        $this->assertStringStartsWith('http://example.com', $uri->__toString());
    }

    public function testWithSchemeAcceptsOnlyHttpAndHttps(): void
    {
        $uri = new Uri('http://example.com/');

        $this->assertSame('https://example.com/', $uri->withScheme('https')->__toString());
        // An unsupported scheme is ignored and the instance is returned unchanged.
        $this->assertSame('https://example.com/', $uri->withScheme('ftp')->__toString());
    }

    public function testWithHostLowercasesAndReplaces(): void
    {
        $uri = (new Uri('http://example.com/'))->withHost('Sub.Example.COM');

        $this->assertSame('http://sub.example.com/', $uri->__toString());
    }

    public function testWithPortAndWithPath(): void
    {
        $uri = (new Uri('http://example.com/'))->withPort(3000)->withPath('/dashboard');

        $this->assertSame('http://example.com:3000/dashboard', $uri->__toString());
    }

    public function testWithersAreFluentAndMutateInPlace(): void
    {
        $uri = new Uri('http://example.com/');

        $this->assertSame($uri, $uri->withPath('/x'));
    }
}
