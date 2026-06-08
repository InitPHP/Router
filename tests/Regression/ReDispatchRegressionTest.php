<?php

declare(strict_types=1);

namespace Tests\Router\Regression;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Bug A3 — the router is single-use per process.
 *
 * `dispatch()` calls `define()` on the INITPHP_ROUTER_CURRENT_* constants
 * without guarding them, so a second successful dispatch in the same process
 * raises "Constant ... already defined" warnings. A router (or two routers)
 * must be dispatchable more than once per process.
 *
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
final class ReDispatchRegressionTest extends RegressionTestCase
{
    public function testTwoDispatchesInOneProcessDoNotRedefineConstants(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        set_error_handler(static function (int $errno, string $errstr): bool {
            if (str_contains($errstr, 'already defined')) {
                throw new RuntimeException($errstr);
            }

            return false;
        });

        try {
            $first = $this->makeRouter('http://localhost/');
            $first->get('/', static fn (): string => 'first');
            $first->dispatch();

            $second = $this->makeRouter('http://localhost/');
            $second->get('/', static fn (): string => 'second');
            $response = $second->dispatch();

            $this->assertInstanceOf(
                ResponseInterface::class,
                $response,
                'A3: a second dispatch in the same process must not redefine global constants.'
            );
        } finally {
            restore_error_handler();
        }
    }
}
