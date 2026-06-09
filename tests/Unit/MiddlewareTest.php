<?php

declare(strict_types=1);

namespace Tests\Router\Unit;

use InitPHP\HTTP\Message\Request;
use InitPHP\HTTP\Message\Response;
use InitPHP\HTTP\Message\Stream;
use InitPHP\Router\Exception\RouterException;
use InitPHP\Router\Router;
use Psr\Http\Message\ResponseInterface;
use Tests\Router\Unit\Fixtures\HeaderMiddleware;
use Tests\Router\Unit\Fixtures\MiddlewareController;
use Tests\Router\Unit\Fixtures\UnauthorizedMiddleware;

require_once __DIR__ . '/Fixtures/DispatchFixtures.php';

final class MiddlewareTest extends \PHPUnit\Framework\TestCase
{
    private function router(string $url = 'http://localhost/'): Router
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $request = new Request('GET', $url, [], null, '1.1');
        $response = new Response(200, [], new Stream('', null), '1.1');

        return new Router($request, $response);
    }

    public function testClassMiddlewareRunsBeforeAndAfterByDefault(): void
    {
        $router = $this->router();
        $router->get('/', static fn (): string => 'body')->filter(HeaderMiddleware::class);

        $response = $router->dispatch();

        $this->assertSame('body', (string) $response->getBody());
        $this->assertSame('ran', $response->getHeaderLine('X-Before'));
        $this->assertSame('ran', $response->getHeaderLine('X-After'));
    }

    public function testBeforeOnlyPositionRunsOnlyBefore(): void
    {
        $router = $this->router();
        $router->get('/', static fn (): string => 'body')->filter(HeaderMiddleware::class, Router::BEFORE);

        $response = $router->dispatch();

        $this->assertSame('ran', $response->getHeaderLine('X-Before'));
        $this->assertSame('', $response->getHeaderLine('X-After'));
    }

    public function testCallableBeforeFilterRuns(): void
    {
        $router = $this->router();
        $router->get('/', static fn (): string => 'body')->filter(
            static fn ($request, ResponseInterface $response, array $arguments): ResponseInterface
                => $response->withHeader('X-Callable', 'yes'),
            Router::BEFORE
        );

        $this->assertSame('yes', $router->dispatch()->getHeaderLine('X-Callable'));
    }

    public function testNonSuccessfulBeforeResponseShortCircuitsTheHandler(): void
    {
        $router = $this->router();
        $router->get('/', static fn (): string => 'should-not-run')->filter(UnauthorizedMiddleware::class, Router::BEFORE);

        $response = $router->dispatch();

        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringNotContainsString('should-not-run', (string) $response->getBody());
    }

    public function testControllerMiddlewaresPropertyIsApplied(): void
    {
        $router = $this->router();
        $router->get('/', MiddlewareController::class . '@index');

        $response = $router->dispatch();

        $this->assertSame('mw-controller', (string) $response->getBody());
        $this->assertSame('ran', $response->getHeaderLine('X-Before'));
    }

    public function testFilterReturningNeitherResponseNorNullThrows(): void
    {
        $router = $this->router();
        $router->get('/', static fn (): string => 'body')->filter(
            static fn ($request, $response, array $arguments): string => 'not-a-response',
            Router::BEFORE
        );

        $this->expectException(RouterException::class);
        $router->dispatch();
    }
}
