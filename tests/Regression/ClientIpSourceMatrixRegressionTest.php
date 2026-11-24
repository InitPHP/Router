<?php

declare(strict_types=1);

namespace Tests\Router\Regression;

use InitPHP\HTTP\Message\Request;
use InitPHP\HTTP\Message\Response;
use InitPHP\HTTP\Message\ServerRequest;
use InitPHP\HTTP\Message\Stream;
use InitPHP\Router\Http\RequestContext;
use InitPHP\Router\Router;
use PHPUnit\Framework\TestCase;

/**
 * The client address must resolve the same way whichever way the request data
 * reaches the router: PHP superglobals (a plain request), a PSR-7 server request
 * built from them (FPM style: server parameters carry the HTTP_* headers), a
 * PSR-7 server request whose server parameters carry only connection facts
 * (ReactPHP style: headers only through getHeader()), an empty server-parameter
 * bag, and a hand-built request with the header only in the server parameters.
 *
 * Trusted proxy is 127.0.0.1 in every cell, and `ip('127.0.0.1')` guards a group.
 */
final class ClientIpSourceMatrixRegressionTest extends TestCase
{
    private const KINDS = ['superglobal', 'psr7-fpm', 'psr7-react', 'psr7-empty-params', 'psr7-params-only'];

    /** @var array<string, mixed> */
    private array $server;

    /** @var array<string, mixed> */
    private array $post;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $this->post = $_POST;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_POST = $this->post;
    }

    /**
     * Each case: remote peer, the X-Forwarded-For header lines as the client/proxies
     * sent them, and the address the router must report.
     *
     * @return array<string, array{string, string, list<string>, ?string}>
     */
    public static function matrix(): array
    {
        $cases = [
            'direct peer, no header'                    => ['127.0.0.1', [], '127.0.0.1'],
            'proxy appends real visitor'                => ['127.0.0.1', ['198.51.100.9'], '198.51.100.9'],
            'forged left entry in one header'           => ['127.0.0.1', ['127.0.0.1, 198.51.100.9'], '198.51.100.9'],
            'forged first of two header lines'          => ['127.0.0.1', ['127.0.0.1', '198.51.100.9'], '198.51.100.9'],
            'untrusted peer forges the header'          => ['203.0.113.5', ['127.0.0.1'], '203.0.113.5'],
            'garbage entry'                             => ['127.0.0.1', ['garbage'], null],
            'visitor spoofs, proxy appends'             => ['127.0.0.1', ['127.0.0.1, 198.51.100.9'], '198.51.100.9'],
        ];

        $matrix = [];
        foreach (self::KINDS as $kind) {
            foreach ($cases as $label => [$remote, $lines, $expected]) {
                $matrix[$kind . ' / ' . $label] = [$kind, $remote, $lines, $expected];
            }
        }

        return $matrix;
    }

    /**
     * @param list<string> $lines
     */
    private function request(string $kind, string $remote, array $lines): Request|ServerRequest
    {
        $joined = \implode(', ', $lines);
        $headers = $lines === [] ? [] : ['X-Forwarded-For' => $lines];
        $withHeaders = static function (ServerRequest $request) use ($lines): ServerRequest {
            foreach (\array_slice($lines, 1) as $line) {
                $request = $request->withAddedHeader('X-Forwarded-For', $line);
            }

            return $request;
        };
        $first = $lines === [] ? [] : ['X-Forwarded-For' => $lines[0]];

        $_SERVER = ['REQUEST_METHOD' => 'GET'];
        switch ($kind) {
            case 'superglobal':
                $_SERVER['REMOTE_ADDR'] = $remote;
                if ($lines !== []) {
                    $_SERVER['HTTP_X_FORWARDED_FOR'] = $joined;
                }

                return new Request('GET', 'http://localhost/internal', $headers, null, '1.1');
            case 'psr7-fpm':
                // $_SERVER deliberately wrong: the request must be the source.
                $_SERVER['REMOTE_ADDR'] = '192.0.2.250';
                $params = ['REMOTE_ADDR' => $remote, 'SERVER_NAME' => 'localhost'];
                if ($lines !== []) {
                    $params['HTTP_X_FORWARDED_FOR'] = $joined;
                }

                return $withHeaders(new ServerRequest('GET', 'http://localhost/internal', $first, null, '1.1', $params));
            case 'psr7-react':
                // No REMOTE_ADDR in $_SERVER (CLI), server params with connection facts only.
                $params = ['REMOTE_ADDR' => $remote, 'REMOTE_PORT' => '51234', 'SERVER_ADDR' => '127.0.0.1', 'SERVER_PORT' => '8080', 'REQUEST_TIME' => 1];

                return $withHeaders(new ServerRequest('GET', 'http://localhost/internal', $first, null, '1.1', $params));
            case 'psr7-empty-params':
                $_SERVER['REMOTE_ADDR'] = $remote;
                if ($lines !== []) {
                    $_SERVER['HTTP_X_FORWARDED_FOR'] = $joined;
                }

                return $withHeaders(new ServerRequest('GET', 'http://localhost/internal', $first, null, '1.1', []));
            case 'psr7-params-only':
                $params = ['REMOTE_ADDR' => $remote];
                if ($lines !== []) {
                    $params['HTTP_X_FORWARDED_FOR'] = $joined;
                }

                return new ServerRequest('GET', 'http://localhost/internal', [], null, '1.1', $params);
        }

        throw new \LogicException('Unknown kind ' . $kind);
    }

    /**
     * @dataProvider matrix
     * @param list<string> $lines
     */
    public function testClientIpIsTheSameWhicheverWayTheRequestDataArrives(string $kind, string $remote, array $lines, ?string $expected): void
    {
        $request = $this->request($kind, $remote, $lines);

        $context = new RequestContext($request, false, Router::SUPPORTED_METHODS, null, null, ['127.0.0.1']);

        $this->assertSame($expected, $context->getClientIp());
    }

    /**
     * @dataProvider matrix
     * @param list<string> $lines
     */
    public function testIpGroupOpensOnlyForTheRealLocalPeer(string $kind, string $remote, array $lines, ?string $expected): void
    {
        $request = $this->request($kind, $remote, $lines);
        $response = new Response(200, [], new Stream('', null), '1.1');

        $router = new Router($request, $response, ['trusted_proxies' => ['127.0.0.1']]);
        $router->ip('127.0.0.1', static function (Router $route): void {
            $route->get('/internal', static fn(): string => 'internal');
        });

        $matched = $router->resolve('GET', 'http://localhost/internal') !== null;

        $this->assertSame($expected === '127.0.0.1', $matched);
    }

    /**
     * @dataProvider matrix
     * @param list<string> $lines
     */
    public function testWithoutTrustedProxiesTheHeaderNeverCounts(string $kind, string $remote, array $lines): void
    {
        $request = $this->request($kind, $remote, $lines);

        $context = new RequestContext($request, false, Router::SUPPORTED_METHODS);

        $this->assertSame($remote, $context->getClientIp());
    }

    /**
     * Method override per source: [kind, real method, POST fields as the request carries them,
     * query-string _method, expected method].
     *
     * @return array<string, array{string, string, array<string, string>, bool, string}>
     */
    public static function methodMatrix(): array
    {
        return [
            'superglobal POST body'              => ['superglobal', 'POST', ['_method' => 'DELETE'], false, 'DELETE'],
            'superglobal GET with query'         => ['superglobal', 'GET', [], true, 'GET'],
            'superglobal POST with query only'   => ['superglobal', 'POST', [], true, 'POST'],
            'superglobal GET with $_POST set'    => ['superglobal', 'GET', ['_method' => 'DELETE'], false, 'GET'],
            'psr7-fpm POST body'                 => ['psr7-fpm', 'POST', ['_method' => 'DELETE'], false, 'DELETE'],
            'psr7-fpm GET with query'            => ['psr7-fpm', 'GET', [], true, 'GET'],
            'psr7-fpm POST with query only'      => ['psr7-fpm', 'POST', [], true, 'POST'],
            'psr7-fpm GET with parsed body'      => ['psr7-fpm', 'GET', ['_method' => 'DELETE'], false, 'GET'],
            'psr7-react POST body'               => ['psr7-react', 'POST', ['_method' => 'PATCH'], false, 'PATCH'],
            'psr7-react GET with query'          => ['psr7-react', 'GET', [], true, 'GET'],
            'psr7-react POST with query only'    => ['psr7-react', 'POST', [], true, 'POST'],
            'psr7-react GET with parsed body'    => ['psr7-react', 'GET', ['_method' => 'DELETE'], false, 'GET'],
        ];
    }

    /**
     * @dataProvider methodMatrix
     * @param array<string, string> $fields
     */
    public function testMethodOverrideIsTheSameWhicheverWayTheRequestDataArrives(string $kind, string $method, array $fields, bool $query, string $expected): void
    {
        $uri = 'http://localhost/articles/42' . ($query ? '?_method=DELETE' : '');
        $_SERVER = ['REMOTE_ADDR' => '192.0.2.1', 'REQUEST_METHOD' => $method];
        $_POST = [];

        if ($kind === 'superglobal') {
            $_POST = $fields;
            $request = new Request($method, $uri, [], null, '1.1');
        } else {
            // FPM-style requests also leave a decoy in $_POST: the request body is the source.
            $_POST = $kind === 'psr7-fpm' ? ['_method' => 'PUT'] : [];
            $params = $kind === 'psr7-fpm' ? ['REMOTE_ADDR' => '192.0.2.1'] : ['REMOTE_ADDR' => '192.0.2.1', 'REMOTE_PORT' => '1'];
            $request = (new ServerRequest($method, $uri, [], null, '1.1', $params))
                ->withQueryParams($query ? ['_method' => 'DELETE'] : [])
                ->withParsedBody($fields === [] ? null : $fields);
        }

        $context = new RequestContext($request, true, Router::SUPPORTED_METHODS);

        $this->assertSame($expected, $context->getMethod());
    }
}
