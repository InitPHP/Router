<?php

declare(strict_types=1);

namespace Tests\Router\Regression;

use InitPHP\Router\Exception\InvalidArgumentException;
use InitPHP\Router\Router;

/**
 * Nested `ip()` groups narrow the access list to the intersection of their
 * levels; the `_method` override never turns a request into ANY or LINK.
 */
final class NestedIpGroupRegressionTest extends RegressionTestCase
{
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

    public function testInnerIpGroupCannotWidenTheOuterList(): void
    {
        $_SERVER['REMOTE_ADDR'] = '2.2.2.2';

        $router = $this->makeRouter();
        $router->ip(['1.1.1.1', '2.2.2.2'], static function (Router $route): void {
            $route->get('/outer', static fn(): string => 'outer');
            $route->ip(['1.1.1.1'], static function (Router $route): void {
                $route->get('/inner', static fn(): string => 'inner');
            });
        });

        $this->assertNotNull($router->resolve('GET', 'http://localhost/outer'));
        $this->assertNull($router->resolve('GET', 'http://localhost/inner'));
    }

    public function testInnerIpGroupStillMatchesAnAddressInBothLists(): void
    {
        $_SERVER['REMOTE_ADDR'] = '1.1.1.1';

        $router = $this->makeRouter();
        $router->ip(['1.1.1.1', '2.2.2.2'], static function (Router $route): void {
            $route->ip('1.1.1.1', static function (Router $route): void {
                $route->get('/inner', static fn(): string => 'inner');
            });
        });

        $this->assertNotNull($router->resolve('GET', 'http://localhost/inner'));
    }

    public function testEmptyIntersectionThrowsAtRegistration(): void
    {
        $router = $this->makeRouter();

        $this->expectException(InvalidArgumentException::class);

        $router->ip(['1.1.1.1'], static function (Router $route): void {
            $route->ip(['2.2.2.2'], static function (Router $route): void {
                $route->get('/never', static fn(): string => 'never');
            });
        });
    }

    public function testPerRouteIpOptionIsIntersectedWithTheGroup(): void
    {
        $router = $this->makeRouter();

        $this->expectException(InvalidArgumentException::class);

        $router->ip(['1.1.1.1'], static function (Router $route): void {
            $route->get('/never', static fn(): string => 'never', ['ip' => '2.2.2.2']);
        });
    }

    public function testIntersectionComparesCanonicalAddresses(): void
    {
        $_SERVER['REMOTE_ADDR'] = '1.1.1.1';

        $router = $this->makeRouter();
        $router->ip('::ffff:1.1.1.1', static function (Router $route): void {
            $route->ip(['1.1.1.1'], static function (Router $route): void {
                $route->get('/inner', static fn(): string => 'inner');
            });
        });

        $this->assertSame(['1.1.1.1'], $router->getRoutes('GET')['http://localhost/inner']['options']['ip']);
        $this->assertNotNull($router->resolve('GET', 'http://localhost/inner'));
    }

    public function testSingleIpGroupIsStoredUnchanged(): void
    {
        $router = $this->makeRouter();
        $router->ip(['2001:DB8::5', '1.1.1.1'], static function (Router $route): void {
            $route->get('/x', static fn(): string => 'x');
        });

        $this->assertSame(['2001:DB8::5', '1.1.1.1'], $router->getRoutes('GET')['http://localhost/x']['options']['ip']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonHttpOverrideProvider(): array
    {
        return ['ANY' => ['ANY'], 'LINK' => ['LINK'], 'lower-case any' => ['any'], 'lower-case link' => ['link']];
    }

    /**
     * @dataProvider nonHttpOverrideProvider
     */
    public function testMethodOverrideToAnyOrLinkKeepsThePostMethod(string $override): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_POST = ['_method' => $override];

        $router = $this->makeRouter('http://localhost/articles/42', 'POST', ['variable_method' => true]);
        $router->post('/articles/{id}', static fn(): string => 'post');

        $this->assertSame('post', (string) $router->dispatch()->getBody());
    }
}
