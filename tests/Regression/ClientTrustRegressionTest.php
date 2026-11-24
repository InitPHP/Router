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
}
