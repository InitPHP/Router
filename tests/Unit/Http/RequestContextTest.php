<?php

declare(strict_types=1);

namespace Tests\Router\Unit\Http;

use InitPHP\HTTP\Message\Request;
use InitPHP\Router\Http\RequestContext;
use PHPUnit\Framework\TestCase;

final class RequestContextTest extends TestCase
{
    private const SUPPORTED = ['GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'PATCH', 'OPTIONS', 'ANY', 'LINK'];

    private function request(string $method = 'GET'): Request
    {
        return new Request($method, 'http://localhost/', [], null, '1.1');
    }

    public function testUsesRequestMethodWhenVariableMethodDisabled(): void
    {
        $context = new RequestContext($this->request('GET'), false, self::SUPPORTED, [], ['_method' => 'DELETE']);

        $this->assertSame('GET', $context->getMethod());
    }

    public function testAppliesMethodOverrideWhenEnabled(): void
    {
        $context = new RequestContext($this->request('POST'), true, self::SUPPORTED, [], ['_method' => 'put']);

        $this->assertSame('PUT', $context->getMethod());
    }

    public function testIgnoresUnsupportedMethodOverride(): void
    {
        $context = new RequestContext($this->request('POST'), true, self::SUPPORTED, [], ['_method' => 'TEAPOT']);

        $this->assertSame('POST', $context->getMethod());
    }

    public function testClientIpPrefersClientIpHeaderThenForwardedThenRemote(): void
    {
        $clientIp = new RequestContext($this->request(), false, self::SUPPORTED, [
            'HTTP_CLIENT_IP'       => '203.0.113.7',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.1',
            'REMOTE_ADDR'          => '10.0.0.1',
        ]);
        $this->assertSame('203.0.113.7', $clientIp->getClientIp());

        $forwarded = new RequestContext($this->request(), false, self::SUPPORTED, [
            'HTTP_X_FORWARDED_FOR' => '198.51.100.1, 10.0.0.9',
            'REMOTE_ADDR'          => '10.0.0.1',
        ]);
        $this->assertSame('198.51.100.1', $forwarded->getClientIp());

        $remote = new RequestContext($this->request(), false, self::SUPPORTED, ['REMOTE_ADDR' => '10.0.0.1']);
        $this->assertSame('10.0.0.1', $remote->getClientIp());
    }

    public function testClientIpIsNullWhenNothingIsAvailable(): void
    {
        $context = new RequestContext($this->request(), false, self::SUPPORTED, []);

        $this->assertNull($context->getClientIp());
    }
}
