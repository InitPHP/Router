<?php

declare(strict_types=1);

namespace Tests\Router\Unit;

use InitPHP\HTTP\Message\Request;
use InitPHP\HTTP\Message\Response;
use InitPHP\HTTP\Message\Stream;
use InitPHP\Router\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Tests\Router\Unit\Fixtures\DispatchController;

require_once __DIR__ . '/Fixtures/DispatchFixtures.php';

final class DispatchTest extends \PHPUnit\Framework\TestCase
{
    private function router(string $url = 'http://localhost/'): Router
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $request = new Request('GET', $url, [], null, '1.1');
        $response = new Response(200, [], new Stream('', null), '1.1');

        return new Router($request, $response);
    }

    public function testCallableReturningStringIsWrittenToBody(): void
    {
        $router = $this->router();
        $router->get('/', static fn (): string => 'Hello World');

        $response = $router->dispatch();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Hello World', (string) $response->getBody());
    }

    public function testCallableReturningResponseReplacesTheResponse(): void
    {
        $router = $this->router();
        $router->get('/', static fn (ResponseInterface $response): ResponseInterface => $response->withStatus(201));

        $this->assertSame(201, $router->dispatch()->getStatusCode());
    }

    public function testCallableReturningStreamBecomesTheBody(): void
    {
        $router = $this->router();
        $router->get('/', static fn (): StreamInterface => new Stream('stream-body', null));

        $this->assertSame('stream-body', (string) $router->dispatch()->getBody());
    }

    public function testEchoedOutputIsCaptured(): void
    {
        $router = $this->router();
        $router->get('/', static function (): void {
            echo 'echoed-output';
        });

        $this->assertSame('echoed-output', (string) $router->dispatch()->getBody());
    }

    public function testControllerStringHandlerIsInvoked(): void
    {
        $router = $this->router();
        $router->get('/', DispatchController::class . '@index');

        $this->assertSame('controller-index', (string) $router->dispatch()->getBody());
        $this->assertSame(DispatchController::class, $router->getCurrentController());
        $this->assertSame('index', $router->getCurrentControllerMethod());
    }

    public function testRouteArgumentsArePassedToTheController(): void
    {
        $router = $this->router('http://localhost/post/42');
        $router->get('/post/{id}', DispatchController::class . '@show');

        $this->assertSame('show:42', (string) $router->dispatch()->getBody());
    }

    public function testHandlerReceivesTheMiddlewareModifiedResponse(): void
    {
        $router = $this->router();
        $router->get('/', static fn (ResponseInterface $response): ResponseInterface => $response)
            ->filter(
                static fn ($req, ResponseInterface $res, $args): ResponseInterface => $res->withHeader('X-Mw', 'on'),
                Router::BEFORE
            );

        $this->assertSame('on', $router->dispatch()->getHeaderLine('X-Mw'));
    }

    public function testNotFoundHandlerIsInvokedWithStatus404(): void
    {
        $router = $this->router('http://localhost/missing');
        $router->get('/', static fn (): string => 'home');
        $router->setNotFoundHandler(static fn (): string => 'not-found-page');

        $response = $router->dispatch();

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('not-found-page', (string) $response->getBody());
    }
}
