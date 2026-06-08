<?php

declare(strict_types=1);

namespace Tests\Router\Regression;

use InitPHP\HTTP\Message\Request;
use InitPHP\HTTP\Message\Response;
use InitPHP\HTTP\Message\Stream;
use InitPHP\Router\Router;
use PHPUnit\Framework\TestCase;

/**
 * Base class for the regression suite.
 *
 * Each test in this suite reproduces one of the high-severity bugs confirmed
 * during the v2 code review. They assert the *correct* behaviour, so they are
 * expected to be RED against the current code and turn GREEN once the matching
 * fix lands in Phase 1.
 */
abstract class RegressionTestCase extends TestCase
{
    protected function makeRouter(
        string $url = 'http://localhost/',
        string $method = 'GET',
        array $configs = []
    ): Router {
        $request = new Request($method, $url, [], null, '1.1');
        $response = new Response(200, [], new Stream('', null), '1.1');

        return new Router($request, $response, $configs);
    }
}
