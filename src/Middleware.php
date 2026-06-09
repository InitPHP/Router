<?php

/**
 * Middleware.php
 *
 * This file is part of Router.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Router;

use Psr\Http\Message\{RequestInterface, ResponseInterface};

abstract class Middleware
{
    /**
     * Runs before the route handler, between the request and the route.
     *
     * @param RequestInterface $request The current request.
     * @param ResponseInterface $response The current response.
     * @param array<int, mixed> $arguments The route's captured arguments, if any.
     * @return ResponseInterface|null The response to use next, or null to halt
     * the pipeline and keep the current response. Any other return throws.
     */
    abstract public function before(RequestInterface $request, ResponseInterface $response, array $arguments = []): ?ResponseInterface;

    /**
     * Runs after the route handler, between the route and the response.
     *
     * @param RequestInterface $request The current request.
     * @param ResponseInterface $response The current response.
     * @param array<int, mixed> $arguments The route's captured arguments, if any.
     * @return ResponseInterface|null The response to use next, or null to halt
     * the pipeline and keep the current response. Any other return throws.
     */
    abstract public function after(RequestInterface $request, ResponseInterface $response, array $arguments = []): ?ResponseInterface;

}
