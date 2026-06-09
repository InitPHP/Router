<?php

declare(strict_types=1);

namespace Tests\Router\Unit;

use InitPHP\HTTP\Message\Request;
use InitPHP\HTTP\Message\Response;
use InitPHP\HTTP\Message\Stream;
use InitPHP\Router\Router;
use Psr\Container\ContainerInterface;
use Tests\Router\Unit\Fixtures\GreetingService;
use Tests\Router\Unit\Fixtures\ServiceController;

require_once __DIR__ . '/Fixtures/DispatchFixtures.php';

/**
 * End-to-end resolution/dispatch coverage for the route-grouping, matching and
 * configuration features that the per-component tests do not exercise together.
 */
final class RouterIntegrationTest extends \PHPUnit\Framework\TestCase
{
    /** @param array<string, mixed> $configs */
    private function router(string $url = 'http://localhost/', string $method = 'GET', array $configs = []): Router
    {
        $_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $request = new Request($method, $url, [], null, '1.1');
        $response = new Response(200, [], new Stream('', null), '1.1');

        return new Router($request, $response, $configs);
    }

    public function testDomainGroupRouteResolvesForMatchingHost(): void
    {
        $router = $this->router('http://admin.example.com/');
        $router->domain('{slug}.example.com', static function (Router $route): void {
            $route->get('/', static fn (): string => 'subdomain-home');
        });

        $response = $router->dispatch();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('subdomain-home', (string) $response->getBody());
        $this->assertSame(['admin'], $router->getCurrentArguments());
    }

    public function testPortGroupRouteResolvesOnlyOnItsPort(): void
    {
        $router = $this->router('http://localhost:9000/');
        $router->port(9000, static fn (Router $route) => $route->get('/', static fn (): string => 'port-9000'));
        $router->port(8080, static fn (Router $route) => $route->get('/', static fn (): string => 'port-8080'));

        $this->assertSame('port-9000', (string) $router->dispatch()->getBody());
    }

    public function testIpGroupAllowsMatchingClientAndBlocksOthers(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.5';
        $allowed = $this->router();
        $allowed->ip('10.0.0.5', static fn (Router $route) => $route->get('/secret', static fn (): string => 'ok'));
        $this->assertNotNull($allowed->resolve('GET', 'http://localhost/secret'));

        $_SERVER['REMOTE_ADDR'] = '10.0.0.9';
        $blocked = $this->router();
        $blocked->ip('10.0.0.5', static fn (Router $route) => $route->get('/secret', static fn (): string => 'ok'));
        $this->assertNull($blocked->resolve('GET', 'http://localhost/secret'));

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    public function testVariableMethodOverridesTheHttpMethod(): void
    {
        $_REQUEST['_method'] = 'PUT';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $router = $this->router('http://localhost/resource', 'POST', ['variable_method' => true]);
        $router->put('/resource', static fn (): string => 'put-handler');

        $body = (string) $router->dispatch()->getBody();
        unset($_REQUEST['_method']);

        $this->assertSame('put-handler', $body);
    }

    public function testCustomWherePatternIsApplied(): void
    {
        $router = $this->router('http://localhost/color/abc123');
        $router->where('hex', '[0-9a-f]+');
        $router->get('/color/:hex', static fn (): string => 'hex-ok');

        $this->assertSame('hex-ok', (string) $router->dispatch()->getBody());
        $this->assertSame(['abc123'], $router->getCurrentArguments());
    }

    public function testCustomWherePatternRejectsNonMatchingValue(): void
    {
        $router = $this->router('http://localhost/color/zzz');
        $router->where('hex', '[0-9a-f]+');
        $router->get('/color/:hex', static fn (): string => 'hex-ok');
        $router->setNotFoundHandler(static fn (): string => 'not-found');

        $response = $router->dispatch();

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('not-found', (string) $response->getBody());
    }

    public function testOptionalParameterResolvesWithAndWithoutTheSegment(): void
    {
        $with = $this->router('http://localhost/admin/dashboard');
        $with->get('/admin/{slug}?', static fn (): string => 'opt');
        $this->assertNotNull($with->resolve('GET', 'http://localhost/admin/dashboard'));

        $without = $this->router('http://localhost/admin/');
        $without->get('/admin/{slug}?', static fn (): string => 'opt');
        $this->assertNotNull($without->resolve('GET', 'http://localhost/admin/'));
    }

    public function testDeprecatedError404AliasInvokesTheHandler(): void
    {
        $router = $this->router('http://localhost/nope');
        $router->get('/', static fn (): string => 'home');
        $router->error_404(static fn (): string => 'custom-404');

        $response = $router->dispatch();

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('custom-404', (string) $response->getBody());
    }

    public function testAnyRouteResolvesForAnyHttpMethod(): void
    {
        $router = $this->router('http://localhost/health', 'DELETE');
        $router->any('/health', static fn (): string => 'any-ok');

        $this->assertSame('any-ok', (string) $router->dispatch()->getBody());
    }

    public function testLinkServesAFileThroughDispatch(): void
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'router-int-');
        file_put_contents($file, 'LINKED-FILE-BODY');

        $router = $this->router('http://localhost/download');
        $router->link('/download', $file);

        $body = (string) $router->dispatch()->getBody();
        @unlink($file);

        $this->assertStringContainsString('LINKED-FILE-BODY', $body);
    }

    public function testControllerDependenciesAreResolvedFromTheContainer(): void
    {
        $controller = new ServiceController(new GreetingService());
        $container = new class ($controller) implements ContainerInterface {
            public function __construct(private object $controller)
            {
            }

            public function get(string $id): mixed
            {
                return $this->controller;
            }

            public function has(string $id): bool
            {
                return true;
            }
        };

        $router = $this->router('http://localhost/', 'GET', ['container' => $container]);
        $router->get('/', ServiceController::class . '@index');

        $this->assertSame('hello-from-service', (string) $router->dispatch()->getBody());
    }
}
