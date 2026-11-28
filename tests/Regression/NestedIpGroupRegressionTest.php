<?php

declare(strict_types=1);

namespace Tests\Router\Regression;

use InitPHP\Router\Exception\InvalidArgumentException;
use InitPHP\Router\Router;
use Tests\Router\Unit\Fixtures\HeaderMiddleware;

require_once __DIR__ . '/../Unit/Fixtures/DispatchFixtures.php';

/**
 * Nested `ip()` groups narrow the access list to the intersection of their
 * levels; the `_method` override never turns a request into ANY or LINK;
 * `middleware` group options accumulate; a group closure that throws still
 * restores the group options.
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

    public function testRouteMiddlewareAddsToTheGroupMiddleware(): void
    {
        $router = $this->makeRouter();
        $router->group('/admin', static function (Router $route): void {
            $route->get('/a', static fn(): string => 'a', ['middleware' => ['Csrf']]);
        }, ['middleware' => ['Auth', 'Log']]);

        $this->assertSame(
            ['Auth', 'Log', 'Csrf'],
            $router->getRoutes('GET')['http://localhost/admin/a']['options']['middleware'],
        );
    }

    public function testInnerGroupMiddlewareAddsToTheOuterGroupMiddleware(): void
    {
        $router = $this->makeRouter();
        $router->group('/admin', static function (Router $route): void {
            $route->group('/sub', static function (Router $route): void {
                $route->get('/a', static fn(): string => 'a');
            }, ['middleware' => ['Csrf']]);
        }, ['middleware' => ['Auth', 'Log']]);

        $this->assertSame(
            ['Auth', 'Log', 'Csrf'],
            $router->getRoutes('GET')['http://localhost/admin/sub/a']['options']['middleware'],
        );
    }

    public function testSingleStringMiddlewareAddsToTheGroupMiddleware(): void
    {
        $router = $this->makeRouter();
        $router->group('/admin', static function (Router $route): void {
            $route->get('/s', static fn(): string => 's', ['middleware' => 'Csrf']);
            $route->group('/sub', static function (Router $route): void {
                $route->get('/t', static fn(): string => 't');
            }, ['middleware' => 'Throttle']);
        }, ['middleware' => ['Auth']]);

        $routes = $router->getRoutes('GET');
        $this->assertSame(['Auth', 'Csrf'], $routes['http://localhost/admin/s']['options']['middleware']);
        $this->assertSame(['Auth', 'Throttle'], $routes['http://localhost/admin/sub/t']['options']['middleware']);
    }

    public function testSingleLevelSingleMiddlewareIsStoredAsAOneEntryList(): void
    {
        $closure = static fn($request, $response, $arguments) => $response;
        $router = $this->makeRouter();
        $router->get('/r', static fn(): string => 'r', ['middleware' => 'Auth']);
        $router->get('/c', static fn(): string => 'c', ['middleware' => $closure]);
        $router->group('/g', static function (Router $route): void {
            $route->get('/x', static fn(): string => 'x');
        }, ['middleware' => 'Auth']);

        $routes = $router->getRoutes('GET');
        $this->assertSame(['Auth'], $routes['http://localhost/r']['options']['middleware']);
        $this->assertSame([$closure], $routes['http://localhost/c']['options']['middleware']);
        $this->assertSame(['Auth'], $routes['http://localhost/g/x']['options']['middleware']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function singleMiddlewareLevelProvider(): array
    {
        return ['route' => ['route', 'class'], 'group' => ['group', 'class'], 'route closure' => ['route', 'closure'], 'group closure' => ['group', 'closure']];
    }

    /**
     * @dataProvider singleMiddlewareLevelProvider
     */
    public function testSingleMiddlewareRunsAtDispatch(string $level, string $kind): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $middleware = $kind === 'class'
            ? HeaderMiddleware::class
            : static fn($request, $response, $arguments) => $response->withHeader('X-Before', 'ran');

        $router = $this->makeRouter($level === 'group' ? 'http://localhost/g/x' : 'http://localhost/x');
        if ($level === 'route') {
            $router->get('/x', static fn(): string => 'body', ['middleware' => $middleware]);
        } else {
            $router->group('/g', static function (Router $route): void {
                $route->get('/x', static fn(): string => 'body');
            }, ['middleware' => $middleware]);
        }

        $response = $router->dispatch();

        $this->assertSame('body', (string) $response->getBody());
        $this->assertSame('ran', $response->getHeaderLine('X-Before'));
    }

    public function testStringGroupMiddlewareAcceptsARouteList(): void
    {
        $router = $this->makeRouter();
        $router->group('/admin', static function (Router $route): void {
            $route->get('/s', static fn(): string => 's', ['middleware' => ['Csrf']]);
        }, ['middleware' => 'Auth']);

        $this->assertSame(['Auth', 'Csrf'], $router->getRoutes('GET')['http://localhost/admin/s']['options']['middleware']);
    }

    public function testMiddlewareBucketsAccumulatePerBucket(): void
    {
        $router = $this->makeRouter();
        $router->group('/admin', static function (Router $route): void {
            $route->get('/a', static fn(): string => 'a', [
                'middleware' => ['Both2', 'before' => ['Before2'], 'after' => ['After2']],
            ]);
        }, ['middleware' => ['Both1', 'before' => ['Before1']]]);

        $this->assertSame(
            ['Both1', 'Both2', 'before' => ['Before1', 'Before2'], 'after' => ['After2']],
            $router->getRoutes('GET')['http://localhost/admin/a']['options']['middleware'],
        );
    }

    public function testGroupMiddlewareStillRunsWhenARouteAddsItsOwn(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $mark = static fn(string $name): \Closure => static fn($request, $response, $arguments) => $response->withAddedHeader('X-Mw', $name);

        $router = $this->makeRouter('http://localhost/admin/a');
        $router->group('/admin', function (Router $route) use ($mark): void {
            $route->get('/a', static fn(\Psr\Http\Message\ResponseInterface $response) => $response, [
                'middleware' => ['before' => [$mark('route')]],
            ]);
        }, ['middleware' => ['before' => [$mark('group')]]]);

        $this->assertSame(['group', 'route'], $router->dispatch()->getHeader('X-Mw'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function groupMethodProvider(): array
    {
        return ['group' => ['group'], 'domain' => ['domain'], 'port' => ['port'], 'ip' => ['ip']];
    }

    private function openGroup(Router $router, string $kind, \Closure $body): void
    {
        match ($kind) {
            'group' => $router->group('/g', $body),
            'domain' => $router->domain('example.com', $body),
            'port' => $router->port(8080, $body),
            'ip' => $router->ip('9.9.9.9', $body),
        };
    }

    /**
     * @dataProvider groupMethodProvider
     */
    public function testGroupOptionsAreRestoredWhenTheGroupClosureThrows(string $kind): void
    {
        $router = $this->makeRouter();

        try {
            $this->openGroup($router, $kind, static function (): void {
                throw new \RuntimeException('boom');
            });
            $this->fail('The closure exception must propagate.');
        } catch (\RuntimeException) {
        }

        $router->get('/after', static fn(): string => 'after');

        $route = $router->getRoutes('GET')['http://localhost/after'] ?? null;
        $this->assertNotNull($route, 'The route must be registered without the failed group\'s prefix/host.');
        $this->assertArrayNotHasKey('ip', $route['options']);
        $this->assertArrayNotHasKey('port', $route['options']);
    }

    public function testFailedNestedGroupDoesNotLeakIntoTheOuterGroup(): void
    {
        $router = $this->makeRouter();
        $router->ip(['1.1.1.1', '2.2.2.2'], function (Router $route): void {
            try {
                $route->ip('3.3.3.3', static function (Router $route): void {
                    $route->get('/never', static fn(): string => 'never');
                });
            } catch (InvalidArgumentException) {
            }
            $route->get('/outer', static fn(): string => 'outer');
        });

        $this->assertSame(
            ['1.1.1.1', '2.2.2.2'],
            $router->getRoutes('GET')['http://localhost/outer']['options']['ip'],
        );
    }
}
