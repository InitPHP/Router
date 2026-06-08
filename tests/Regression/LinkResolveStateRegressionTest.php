<?php

declare(strict_types=1);

namespace Tests\Router\Regression;

use Psr\Http\Message\ResponseInterface;

/**
 * Bug A5 — `resolve()` corrupts state for LINK routes.
 *
 * When a LINK route matches, `resolve()` runs `return $this->current_method = $route;`
 * (a typo for `current_route`). It writes a route *array* into `current_method`
 * and never sets `current_route`, so calling `resolve()` before `dispatch()`
 * makes `dispatch()` re-resolve with an array as the HTTP method and fatal with
 * a TypeError.
 */
final class LinkResolveStateRegressionTest extends RegressionTestCase
{
    private string $file = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->file = (string) tempnam(sys_get_temp_dir(), 'router-link-');
        file_put_contents($this->file, 'FILE-LINK-OK');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);

        parent::tearDown();
    }

    public function testResolvingFileLinkThenDispatchingServesTheFile(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $router = $this->makeRouter();
        $router->link('/download', $this->file);

        $router->resolve('GET', 'http://localhost/download');
        $response = $router->dispatch();

        $this->assertInstanceOf(
            ResponseInterface::class,
            $response,
            'A5: resolving a link route before dispatch() must not corrupt the dispatch state.'
        );
        $this->assertStringContainsString('FILE-LINK-OK', (string) $response->getBody());
    }
}
