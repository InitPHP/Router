<?php

declare(strict_types=1);

namespace Tests\Router\Unit\Dispatch;

use InitPHP\HTTP\Message\Request;
use InitPHP\HTTP\Message\Response;
use InitPHP\HTTP\Message\Stream;
use InitPHP\Router\Dispatch\MiddlewareRunner;
use InitPHP\Router\Exception\RouterException;
use InitPHP\Router\Resolver\ParameterResolver;
use Psr\Http\Message\ResponseInterface;
use Tests\Router\Unit\Fixtures\HeaderMiddleware;
use Tests\Router\Unit\Fixtures\MiddlewareController;
use Tests\Router\Unit\Fixtures\RichMiddlewareController;

require_once __DIR__ . '/../Fixtures/DispatchFixtures.php';

final class MiddlewareRunnerTest extends \PHPUnit\Framework\TestCase
{
    private Request $request;
    private Response $response;

    protected function setUp(): void
    {
        $this->request = new Request('GET', 'http://localhost/', [], null, '1.1');
        $this->response = new Response(200, [], new Stream('', null), '1.1');
    }

    private function runner(): MiddlewareRunner
    {
        return new MiddlewareRunner($this->request, new ParameterResolver($this->request));
    }

    public function testCallableFilterReplacesResponseAndContinues(): void
    {
        $result = $this->runner()->run(
            [static fn ($req, ResponseInterface $res, $args): ResponseInterface => $res->withHeader('X-Test', 'yes')],
            $this->response,
            [],
            true
        );

        $this->assertFalse($result->halted);
        $this->assertSame('yes', $result->response->getHeaderLine('X-Test'));
    }

    public function testNonSuccessResponseHaltsThePipeline(): void
    {
        $result = $this->runner()->run(
            [static fn ($req, ResponseInterface $res, $args): ResponseInterface => $res->withStatus(403)],
            $this->response,
            [],
            true
        );

        $this->assertTrue($result->halted);
        $this->assertSame(403, $result->response->getStatusCode());
    }

    public function testNullReturnHaltsWithoutExiting(): void
    {
        // B3 regression: returning null used to call exit() and kill the process.
        $result = $this->runner()->run(
            [static fn ($req, $res, $args) => null],
            $this->response,
            [],
            true
        );

        $this->assertTrue($result->halted);
        $this->assertSame($this->response, $result->response);
    }

    public function testInvalidReturnTypeThrows(): void
    {
        $this->expectException(RouterException::class);
        $this->runner()->run(
            [static fn ($req, $res, $args): string => 'nope'],
            $this->response,
            [],
            true
        );
    }

    public function testClassMiddlewareRunsRequestedMethod(): void
    {
        $before = $this->runner()->run([HeaderMiddleware::class], $this->response, [], true);
        $this->assertSame('ran', $before->response->getHeaderLine('X-Before'));
        $this->assertSame('', $before->response->getHeaderLine('X-After'));

        $after = $this->runner()->run([HeaderMiddleware::class], $this->response, [], false);
        $this->assertSame('ran', $after->response->getHeaderLine('X-After'));
    }

    public function testUnknownClassFilterThrows(): void
    {
        $this->expectException(RouterException::class);
        $this->runner()->run(['NoSuchMiddleware'], $this->response, [], true);
    }

    public function testControllerMiddlewaresExtractsBeforeListForTheMethod(): void
    {
        $controller = new MiddlewareController();
        $runner = $this->runner();

        $this->assertSame([HeaderMiddleware::class], $runner->controllerMiddlewares($controller, 'index', true));
        $this->assertSame([], $runner->controllerMiddlewares($controller, 'index', false));
    }

    public function testControllerMiddlewaresFlattensGlobalAndMethodSpecificEntries(): void
    {
        $controller = new RichMiddlewareController();
        $runner = $this->runner();

        $this->assertSame(
            ['GlobalBefore', 'GlobalFilter', 'IndexBefore'],
            $runner->controllerMiddlewares($controller, 'index', true)
        );
        $this->assertSame(
            ['GlobalAfter', 'GlobalFilter', 'IndexAfter'],
            $runner->controllerMiddlewares($controller, 'index', false)
        );
        // A method without its own entry gets only the global filters.
        $this->assertSame(
            ['GlobalBefore', 'GlobalFilter'],
            $runner->controllerMiddlewares($controller, 'other', true)
        );
    }

    public function testClassFilterIsResolvedViaTheMiddlewareNamespace(): void
    {
        $result = $this->runner()->run(
            ['HeaderMiddleware'],
            $this->response,
            [],
            true,
            ['namespaces' => ['middleware' => 'Tests\\Router\\Unit\\Fixtures']]
        );

        $this->assertSame('ran', $result->response->getHeaderLine('X-Before'));
    }
}
