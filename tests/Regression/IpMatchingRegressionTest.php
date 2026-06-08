<?php

declare(strict_types=1);

namespace Tests\Router\Regression;

use InitPHP\Router\Router;

/**
 * Bug A1 — IP-restricted routes never match.
 *
 * `confirm_ip_addresses()` always stores the allow-list as an array, but
 * `resolve()` then compares that array against the (string) client IP with
 * `!=`, which is always true, so every IP-scoped route is skipped — even for
 * a client whose address is in the allow-list.
 */
final class IpMatchingRegressionTest extends RegressionTestCase
{
    public function testClientIpInAllowListMatchesRoute(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.10';

        $router = $this->makeRouter();
        $router->ip(['192.168.1.10', '192.168.1.11'], static function (Router $route): void {
            $route->get('/secret', ['Admin', 'secret']);
        });

        $resolved = $router->resolve('GET', 'http://localhost/secret');

        $this->assertNotNull(
            $resolved,
            'A1: a client IP that is in the allow-list must match the IP-scoped route.'
        );
        $this->assertSame(['Admin', 'secret'], $resolved['execute']);
    }

    public function testClientIpOutsideAllowListDoesNotMatchRoute(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.5';

        $router = $this->makeRouter();
        $router->ip(['192.168.1.10', '192.168.1.11'], static function (Router $route): void {
            $route->get('/secret', ['Admin', 'secret']);
        });

        $this->assertNull(
            $router->resolve('GET', 'http://localhost/secret'),
            'A client IP outside the allow-list must not match the IP-scoped route.'
        );
    }
}
