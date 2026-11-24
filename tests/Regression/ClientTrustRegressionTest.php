<?php

declare(strict_types=1);

namespace Tests\Router\Regression;

use InitPHP\Router\Exception\InvalidArgumentException;
use InitPHP\Router\Router;

/**
 * `ip()` groups and the `_method` override must not be steerable by request data
 * the client controls: forwarding headers count only behind a configured trusted
 * proxy, and `_method` counts only on a real POST (never from the query string).
 */
final class ClientTrustRegressionTest extends RegressionTestCase
{
    /** @var array<string, mixed> */
    private array $server;

    /** @var array<string, mixed> */
    private array $post;

    /** @var array<string, mixed> */
    private array $request;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $this->post = $_POST;
        $this->request = $_REQUEST;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_POST = $this->post;
        $_REQUEST = $this->request;
    }

    private function internalRoutes(Router $router): void
    {
        $router->ip('127.0.0.1', static function (Router $route): void {
            $route->get('/internal', static fn(): string => 'internal');
        });
    }

    public function testClientIpHeaderCannotImpersonateAnAllowedAddress(): void
    {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.9';
        $_SERVER['HTTP_CLIENT_IP'] = '127.0.0.1';

        $router = $this->makeRouter();
        $this->internalRoutes($router);

        $this->assertNull($router->resolve('GET', 'http://localhost/internal'));
    }

    public function testForwardedForCannotImpersonateWithoutATrustedProxy(): void
    {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.9';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '127.0.0.1';

        $router = $this->makeRouter();
        $this->internalRoutes($router);

        $this->assertNull($router->resolve('GET', 'http://localhost/internal'));
    }

    public function testForwardedForCannotImpersonateThroughATrustedProxy(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        // The proxy appended the real peer; the leading entry was sent by the client.
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '127.0.0.1, 198.51.100.9';

        $router = $this->makeRouter(configs: ['trusted_proxies' => ['10.0.0.0/8']]);
        $this->internalRoutes($router);

        $this->assertNull($router->resolve('GET', 'http://localhost/internal'));
    }

    public function testForwardedForIsHonouredThroughATrustedProxy(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '127.0.0.1';

        $router = $this->makeRouter(configs: ['trusted_proxies' => ['10.0.0.0/8']]);
        $this->internalRoutes($router);

        $this->assertNotNull($router->resolve('GET', 'http://localhost/internal'));
    }

    public function testTrustedProxiesIsNotLeakedIntoRouteOptions(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';

        $router = $this->makeRouter(configs: ['trusted_proxies' => ['10.0.0.0/8']]);
        $router->get('/x', static fn(): string => 'x');

        $this->assertArrayNotHasKey('trusted_proxies', $router->getRoutes('GET')['http://localhost/x']['options']);
    }

    public function testInvalidTrustedProxyFailsFast(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeRouter(configs: ['trusted_proxies' => ['10.0.0.0/99']]);
    }

    public function testMethodOverrideInTheQueryStringOfAGetRequestIsIgnored(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_GET['_method'] = 'DELETE';
        $_REQUEST['_method'] = 'DELETE';

        try {
            $router = $this->makeRouter('http://localhost/articles/42?_method=DELETE', 'GET', ['variable_method' => true]);
            $router->get('/articles/{id}', static fn(): string => 'show');
            $router->delete('/articles/{id}', static fn(): string => 'destroy');

            $body = (string) $router->dispatch()->getBody();
        } finally {
            unset($_GET['_method']);
        }

        $this->assertSame('show', $body);
    }

    public function testMethodOverrideInTheQueryStringOfAPostRequestIsIgnored(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_POST = [];
        $_REQUEST['_method'] = 'DELETE';

        $router = $this->makeRouter('http://localhost/articles/42?_method=DELETE', 'POST', ['variable_method' => true]);
        $router->post('/articles/{id}', static fn(): string => 'update');
        $router->delete('/articles/{id}', static fn(): string => 'destroy');

        $this->assertSame('update', (string) $router->dispatch()->getBody());
    }

    public function testMethodOverrideInThePostBodyOfAPostRequestApplies(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_POST = ['_method' => 'DELETE'];

        $router = $this->makeRouter('http://localhost/articles/42', 'POST', ['variable_method' => true]);
        $router->post('/articles/{id}', static fn(): string => 'update');
        $router->delete('/articles/{id}', static fn(): string => 'destroy');

        $this->assertSame('destroy', (string) $router->dispatch()->getBody());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidTrustedProxiesOption(): array
    {
        return [
            'string'  => ['127.0.0.1'],
            'bool'    => [true],
            'int'     => [10],
            'false'   => [false],
        ];
    }

    /**
     * @dataProvider invalidTrustedProxiesOption
     * @param mixed $value
     */
    public function testNonArrayTrustedProxiesFailsFastInsteadOfOpeningTheGroup(mixed $value): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.9';

        $this->expectException(InvalidArgumentException::class);

        $this->makeRouter(configs: ['trusted_proxies' => $value]);
    }

    public function testNullTrustedProxiesMeansNone(): void
    {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.9';

        $router = $this->makeRouter(configs: ['trusted_proxies' => null]);
        $this->internalRoutes($router);

        $this->assertNull($router->resolve('GET', 'http://localhost/internal'));
    }

    public function testSubclassWithoutTheTrustedProxiesKeyDoesNotWarn(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.0.2.1';
        $request = new \InitPHP\HTTP\Message\Request('GET', 'http://localhost/', [], null, '1.1');
        $response = new \InitPHP\HTTP\Message\Response(200, [], new \InitPHP\HTTP\Message\Stream('', null), '1.1');

        $router = new class ($request, $response) extends Router {
            /** @var array<string, mixed> */
            protected array $configs = [
                'paths'                 => ['controller' => null, 'middleware' => null],
                'namespaces'            => ['controller' => null, 'middleware' => null],
                'base_path'             => '/',
                'variable_method'       => false,
                'argument_new_instance' => false,
            ];
        };

        $this->assertInstanceOf(Router::class, $router);
    }

    public function testMappedIpv6AllowListEntryMatchesTheSameClientInAnyForm(): void
    {
        foreach (['::ffff:192.0.2.10', '192.0.2.10'] as $remote) {
            $_SERVER['REMOTE_ADDR'] = $remote;

            $router = $this->makeRouter();
            $router->ip('::ffff:192.0.2.10', static function (Router $route): void {
                $route->get('/banner', static fn(): string => 'banner');
            });

            $this->assertNotNull($router->resolve('GET', 'http://localhost/banner'), $remote);
        }
    }

    public function testUpperCaseIpv6AllowListEntryMatchesTheCanonicalClient(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '2001:DB8::5';

        $router = $this->makeRouter(configs: ['trusted_proxies' => ['10.0.0.1']]);
        $router->ip(['2001:DB8::5', '2001:db8:0:0:0:0:0:6'], static function (Router $route): void {
            $route->get('/banner', static fn(): string => 'banner');
        });

        $this->assertNotNull($router->resolve('GET', 'http://localhost/banner'));
    }

    public function testAllowListNormalisationDoesNotWidenTheMatch(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.0.2.11';

        $router = $this->makeRouter();
        $router->ip('::ffff:192.0.2.10', static function (Router $route): void {
            $route->get('/banner', static fn(): string => 'banner');
        });

        $this->assertNull($router->resolve('GET', 'http://localhost/banner'));
    }
}
