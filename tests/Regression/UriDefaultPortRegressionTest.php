<?php

declare(strict_types=1);

namespace Tests\Router\Regression;

use InitPHP\Router\Uri;
use PHPUnit\Framework\TestCase;

/**
 * Bug A2 — default ports are never dropped.
 *
 * `Uri::boot()` guards with `($port != 80 || $port != 443)`, which is a
 * tautology (no value equals both), so the port is always retained and the
 * intended "strip the default HTTP/HTTPS port" never happens.
 */
final class UriDefaultPortRegressionTest extends TestCase
{
    public function testDefaultHttpPortIsDropped(): void
    {
        $uri = new Uri('http://example.com:80/path');

        $this->assertSame(
            'http://example.com/path',
            $uri->__toString(),
            'A2: the default HTTP port (80) must not appear in the generated URI.'
        );
    }

    public function testDefaultHttpsPortIsDropped(): void
    {
        $uri = new Uri('https://example.com:443/path');

        $this->assertSame(
            'https://example.com/path',
            $uri->__toString(),
            'A2: the default HTTPS port (443) must not appear in the generated URI.'
        );
    }

    public function testNonDefaultPortIsKept(): void
    {
        $uri = new Uri('http://example.com:8080/path');

        $this->assertSame('http://example.com:8080/path', $uri->__toString());
    }
}
