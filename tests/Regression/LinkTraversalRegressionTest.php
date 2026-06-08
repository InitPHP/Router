<?php

declare(strict_types=1);

namespace Tests\Router\Regression;

/**
 * Bug A4 — directory links are vulnerable to path traversal.
 *
 * A directory link registers `link/(.*)` and serves `$source . $captured`
 * with no containment check, so a request such as `/assets/../secret.txt`
 * escapes the link root. A traversal request must not match (and must never
 * serve a file outside the link root).
 */
final class LinkTraversalRegressionTest extends RegressionTestCase
{
    private string $base = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir() . '/router-regression-' . bin2hex(random_bytes(6));
        mkdir($this->base . '/public', 0777, true);
        file_put_contents($this->base . '/public/app.css', 'body{}');
        file_put_contents($this->base . '/secret.txt', 'TOPSECRET');
    }

    protected function tearDown(): void
    {
        @unlink($this->base . '/public/app.css');
        @unlink($this->base . '/secret.txt');
        @rmdir($this->base . '/public');
        @rmdir($this->base);

        parent::tearDown();
    }

    public function testDirectoryLinkRejectsPathTraversal(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $router = $this->makeRouter();
        // Trailing slash makes the naive `$source . $captured` concatenation
        // resolve straight onto ../secret.txt with today's code.
        $router->link('/assets', $this->base . '/public/');

        $resolved = $router->resolve('GET', 'http://localhost/assets/../secret.txt');

        $this->assertNull(
            $resolved,
            'A4: a path-traversal request must not match a directory link.'
        );
    }

    public function testDirectoryLinkStillMatchesAFileInsideTheRoot(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $router = $this->makeRouter();
        $router->link('/assets', $this->base . '/public/');

        $this->assertNotNull(
            $router->resolve('GET', 'http://localhost/assets/app.css'),
            'A legitimate file inside the link root must still match.'
        );
    }
}
