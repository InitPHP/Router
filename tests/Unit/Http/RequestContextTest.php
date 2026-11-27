<?php

declare(strict_types=1);

namespace Tests\Router\Unit\Http;

use InitPHP\HTTP\Message\Request;
use InitPHP\HTTP\Message\ServerRequest;
use InitPHP\Router\Exception\InvalidArgumentException;
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

    public function testMethodOverrideToAnyOrLinkIsIgnored(): void
    {
        foreach (['ANY', 'any', 'LINK', 'link'] as $override) {
            $context = new RequestContext($this->request('POST'), true, self::SUPPORTED, [], ['_method' => $override]);

            $this->assertSame('POST', $context->getMethod(), $override . ' must not override');
        }
    }

    public function testMethodOverrideRequiresARealPostRequest(): void
    {
        foreach (['GET', 'HEAD', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'] as $real) {
            $context = new RequestContext($this->request($real), true, self::SUPPORTED, [], ['_method' => 'DELETE']);

            $this->assertSame($real, $context->getMethod(), $real . ' must not be overridden');
        }
    }

    public function testMethodOverrideIsNotReadFromRequestSuperglobal(): void
    {
        $_REQUEST['_method'] = 'DELETE';
        $_POST = [];

        try {
            $context = new RequestContext($this->request('POST'), true, self::SUPPORTED, []);
            $query = new RequestContext($this->request('GET'), true, self::SUPPORTED, []);
        } finally {
            unset($_REQUEST['_method']);
        }

        $this->assertSame('POST', $context->getMethod());
        $this->assertSame('GET', $query->getMethod());
    }

    public function testMethodOverrideIsReadFromPostSuperglobalByDefault(): void
    {
        $_POST = ['_method' => 'delete'];

        try {
            $context = new RequestContext($this->request('POST'), true, self::SUPPORTED, []);
        } finally {
            $_POST = [];
        }

        $this->assertSame('DELETE', $context->getMethod());
    }

    public function testMethodOverrideIsReadFromTheParsedBodyOfAServerRequest(): void
    {
        $_POST = [];
        $request = (new ServerRequest('POST', 'http://localhost/', [], null, '1.1'))
            ->withParsedBody(['_method' => 'PATCH']);

        $context = new RequestContext($request, true, self::SUPPORTED, []);

        $this->assertSame('PATCH', $context->getMethod());
    }

    public function testNonStringMethodOverrideIsIgnored(): void
    {
        $context = new RequestContext($this->request('POST'), true, self::SUPPORTED, [], ['_method' => ['DELETE']]);

        $this->assertSame('POST', $context->getMethod());
    }

    public function testClientIpIsRemoteAddrByDefault(): void
    {
        $context = new RequestContext($this->request(), false, self::SUPPORTED, [
            'HTTP_CLIENT_IP'       => '203.0.113.7',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.1',
            'REMOTE_ADDR'          => '10.0.0.1',
        ]);

        $this->assertSame('10.0.0.1', $context->getClientIp());
    }

    public function testClientIpIsNullWhenNothingIsAvailable(): void
    {
        $context = new RequestContext($this->request(), false, self::SUPPORTED, []);

        $this->assertNull($context->getClientIp());
    }

    public function testClientIpIsNullWhenOnlyForwardingHeadersArePresent(): void
    {
        $context = new RequestContext($this->request(), false, self::SUPPORTED, [
            'HTTP_X_FORWARDED_FOR' => '198.51.100.1',
        ], null, ['10.0.0.0/8']);

        $this->assertNull($context->getClientIp());
    }

    public function testClientIpIsNullWhenRemoteAddrIsNotAnIpAddress(): void
    {
        $context = new RequestContext($this->request(), false, self::SUPPORTED, ['REMOTE_ADDR' => 'not-an-ip']);

        $this->assertNull($context->getClientIp());
    }

    public function testForwardedHeaderIsIgnoredWhenRemoteAddrIsNotATrustedProxy(): void
    {
        $context = new RequestContext($this->request(), false, self::SUPPORTED, [
            'REMOTE_ADDR'          => '198.51.100.9',
            'HTTP_X_FORWARDED_FOR' => '127.0.0.1',
            'HTTP_CLIENT_IP'       => '127.0.0.1',
        ], null, ['10.0.0.0/8']);

        $this->assertSame('198.51.100.9', $context->getClientIp());
    }

    public function testClientIpHeaderIsNeverReadEvenFromATrustedProxy(): void
    {
        $context = new RequestContext($this->request(), false, self::SUPPORTED, [
            'REMOTE_ADDR'    => '10.0.0.1',
            'HTTP_CLIENT_IP' => '127.0.0.1',
        ], null, ['10.0.0.1']);

        $this->assertSame('10.0.0.1', $context->getClientIp());
    }

    public function testForwardedHeaderIsReadWhenRemoteAddrIsATrustedProxy(): void
    {
        $context = new RequestContext($this->request(), false, self::SUPPORTED, [
            'REMOTE_ADDR'          => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.1',
        ], null, ['10.0.0.1']);

        $this->assertSame('198.51.100.1', $context->getClientIp());
    }

    public function testForwardedChainIsResolvedFromTheRightSkippingTrustedProxies(): void
    {
        $context = new RequestContext($this->request(), false, self::SUPPORTED, [
            'REMOTE_ADDR'          => '10.0.0.1',
            // 127.0.0.1 was forged by the client; 198.51.100.1 is the address the first proxy saw.
            'HTTP_X_FORWARDED_FOR' => '127.0.0.1, 198.51.100.1, 10.0.0.7, 10.0.0.2',
        ], null, ['10.0.0.0/24']);

        $this->assertSame('198.51.100.1', $context->getClientIp());
    }

    public function testForgedLeftmostEntryDoesNotWin(): void
    {
        $context = new RequestContext($this->request(), false, self::SUPPORTED, [
            'REMOTE_ADDR'          => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '192.0.2.99, 198.51.100.1',
        ], null, ['10.0.0.1']);

        $this->assertSame('198.51.100.1', $context->getClientIp());
    }

    public function testChainOfOnlyTrustedProxiesYieldsTheLeftmostEntry(): void
    {
        $context = new RequestContext($this->request(), false, self::SUPPORTED, [
            'REMOTE_ADDR'          => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '10.0.0.5, 10.0.0.6',
        ], null, ['10.0.0.0/8']);

        $this->assertSame('10.0.0.5', $context->getClientIp());
    }

    public function testInvalidForwardedEntryIsRejected(): void
    {
        foreach (['unknown', '198.51.100.1:8080', '', '999.1.1.1', '198.51.100.1, , 10.0.0.2'] as $header) {
            $context = new RequestContext($this->request(), false, self::SUPPORTED, [
                'REMOTE_ADDR'          => '10.0.0.1',
                'HTTP_X_FORWARDED_FOR' => $header,
            ], null, ['10.0.0.0/8']);

            if ($header === '') {
                $this->assertSame('10.0.0.1', $context->getClientIp(), 'an empty header falls back to REMOTE_ADDR');
                continue;
            }
            $this->assertNull($context->getClientIp(), 'header "' . $header . '" must not yield an address');
        }
    }

    public function testInvalidEntryLeftOfTheClientDoesNotMatter(): void
    {
        $context = new RequestContext($this->request(), false, self::SUPPORTED, [
            'REMOTE_ADDR'          => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => 'garbage, 198.51.100.1',
        ], null, ['10.0.0.1']);

        $this->assertSame('198.51.100.1', $context->getClientIp());
    }

    public function testIpv4MappedIpv6RemoteAddrIsNormalised(): void
    {
        $context = new RequestContext($this->request(), false, self::SUPPORTED, ['REMOTE_ADDR' => '::ffff:192.0.2.10']);

        $this->assertSame('192.0.2.10', $context->getClientIp());
    }

    public function testIpv4MappedIpv6AddressMatchesIpv4TrustedProxy(): void
    {
        $context = new RequestContext($this->request(), false, self::SUPPORTED, [
            'REMOTE_ADDR'          => '::ffff:10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '::ffff:198.51.100.1',
        ], null, ['10.0.0.0/8']);

        $this->assertSame('198.51.100.1', $context->getClientIp());
    }

    public function testIpv4MappedTrustedProxyEntryIsNormalised(): void
    {
        $context = new RequestContext($this->request(), false, self::SUPPORTED, [
            'REMOTE_ADDR'          => '10.1.2.3',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.1',
        ], null, ['::ffff:10.0.0.0/104']);

        $this->assertSame('198.51.100.1', $context->getClientIp());
    }

    public function testIpv6TrustedProxyAndChain(): void
    {
        $context = new RequestContext($this->request(), false, self::SUPPORTED, [
            'REMOTE_ADDR'          => 'fd00::1',
            'HTTP_X_FORWARDED_FOR' => '2001:DB8::5, fd00::2',
        ], null, ['fd00::/8']);

        $this->assertSame('2001:db8::5', $context->getClientIp());
    }

    public function testCidrBoundaries(): void
    {
        $trusted = ['10.0.0.0/25', '192.0.2.4/31', '203.0.113.9'];
        $cases = [
            '10.0.0.127'  => true,
            '10.0.0.128'  => false,
            '192.0.2.4'   => true,
            '192.0.2.5'   => true,
            '192.0.2.6'   => false,
            '203.0.113.9' => true,
            '203.0.113.8' => false,
        ];
        foreach ($cases as $remote => $isTrusted) {
            $context = new RequestContext($this->request(), false, self::SUPPORTED, [
                'REMOTE_ADDR'          => $remote,
                'HTTP_X_FORWARDED_FOR' => '198.51.100.1',
            ], null, $trusted);

            $this->assertSame($isTrusted ? '198.51.100.1' : $remote, $context->getClientIp(), $remote);
        }
    }

    public function testZeroPrefixTrustsEveryAddressOfTheFamily(): void
    {
        $context = new RequestContext($this->request(), false, self::SUPPORTED, [
            'REMOTE_ADDR'          => '192.0.2.77',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.1',
        ], null, ['0.0.0.0/0']);

        $this->assertSame('198.51.100.1', $context->getClientIp());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidTrustedProxies(): array
    {
        return [
            'not an ip'          => ['proxy.example.com'],
            'empty'              => [''],
            'empty prefix'       => ['10.0.0.0/'],
            'non numeric prefix' => ['10.0.0.0/abc'],
            'negative prefix'    => ['10.0.0.0/-1'],
            'ipv4 prefix too big' => ['10.0.0.0/33'],
            'ipv6 prefix too big' => ['fd00::/129'],
            'mapped prefix too small' => ['::ffff:10.0.0.0/64'],
        ];
    }

    /**
     * @dataProvider invalidTrustedProxies
     */
    public function testInvalidTrustedProxyIsRejected(string $proxy): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RequestContext($this->request(), false, self::SUPPORTED, [], null, [$proxy]);
    }

    public function testNonStringTrustedProxyIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RequestContext($this->request(), false, self::SUPPORTED, [], null, [10]);
    }

    public function testServerRequestParamsWinOverTheSuperglobal(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $request = new ServerRequest('GET', 'http://localhost/', [], null, '1.1', ['REMOTE_ADDR' => '198.51.100.9']);

        $context = new RequestContext($request, false, self::SUPPORTED);

        $this->assertSame('198.51.100.9', $context->getClientIp());
    }

    public function testServerRequestWithoutServerParamsFallsBackToTheSuperglobal(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.0.2.44';
        $request = new ServerRequest('GET', 'http://localhost/', [], null, '1.1');

        $context = new RequestContext($request, false, self::SUPPORTED);

        $this->assertSame('192.0.2.44', $context->getClientIp());
    }

    public function testServerRequestWithoutParsedBodyIgnoresThePostSuperglobal(): void
    {
        $_POST = ['_method' => 'DELETE'];

        try {
            $context = new RequestContext(
                (new ServerRequest('POST', 'http://localhost/', [], null, '1.1', ['REMOTE_ADDR' => '192.0.2.1']))->withParsedBody(null),
                true,
                self::SUPPORTED,
            );
        } finally {
            $_POST = [];
        }

        $this->assertSame('POST', $context->getMethod());
    }

    public function testServerRequestWithObjectParsedBodyIgnoresThePostSuperglobal(): void
    {
        $_POST = ['_method' => 'DELETE'];
        $request = (new ServerRequest('POST', 'http://localhost/', [], null, '1.1', ['REMOTE_ADDR' => '192.0.2.1']))
            ->withParsedBody((object) ['_method' => 'PUT']);

        try {
            $context = new RequestContext($request, true, self::SUPPORTED);
        } finally {
            $_POST = [];
        }

        $this->assertSame('PUT', $context->getMethod());
    }

    public function testNormalizeIp(): void
    {
        $this->assertSame('192.0.2.10', RequestContext::normalizeIp('::ffff:192.0.2.10'));
        $this->assertSame('192.0.2.10', RequestContext::normalizeIp('::FFFF:C000:020A'));
        $this->assertSame('2001:db8::5', RequestContext::normalizeIp('2001:DB8:0:0:0:0:0:5'));
        $this->assertSame('10.0.0.1', RequestContext::normalizeIp(' 10.0.0.1 '));
        $this->assertNull(RequestContext::normalizeIp('not-an-ip'));
    }

    public function testInjectedServerParamsAreNotOverriddenByTheRequestHeader(): void
    {
        $request = new ServerRequest('GET', 'http://localhost/', ['X-Forwarded-For' => '127.0.0.1'], null, '1.1', ['REMOTE_ADDR' => '10.0.0.1']);

        $injected = new RequestContext($request, false, self::SUPPORTED, [
            'REMOTE_ADDR'          => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.9',
        ], null, ['10.0.0.1']);
        $injectedWithoutHeader = new RequestContext($request, false, self::SUPPORTED, ['REMOTE_ADDR' => '10.0.0.1'], null, ['10.0.0.1']);
        $fromRequest = new RequestContext($request, false, self::SUPPORTED, null, null, ['10.0.0.1']);

        $this->assertSame('198.51.100.9', $injected->getClientIp());
        $this->assertSame('10.0.0.1', $injectedWithoutHeader->getClientIp());
        $this->assertSame('127.0.0.1', $fromRequest->getClientIp());
    }
}
