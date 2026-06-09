<?php

/**
 * Middleware.php
 *
 * This file is part of Router.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.1.3
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Router;

use Psr\Http\Message\{RequestInterface, ResponseInterface};

abstract class Middleware
{
    /**
     * The method to run before the routes start executing.
     *
     * This method works between the request and the route.
     *
     * @param RequestInterface $request <p>The request object used.</p>
     * @param ResponseInterface $response <p>The response object used.</p>
     * @param array<int, mixed> $arguments <p>Array holding the route's arguments, if any.</p>
     * @return ResponseInterface|null <p>
     * Return the response to use next, or null to halt the pipeline and keep
     * the current response as-is. Returning any other type throws an exception.
     * </p>
     */
    abstract public function before(RequestInterface $request, ResponseInterface $response, array $arguments = []): ?ResponseInterface;

    /**
     * The method to run after the routes finish executing.
     *
     * This method works between route and response.
     *
     * @param RequestInterface $request <p>The request object used.</p>
     * @param ResponseInterface $response <p>The response object used.</p>
     * @param array<int, mixed> $arguments <p>Array holding the route's arguments, if any.</p>
     * @return ResponseInterface|null <p>
     * Return the response to use next, or null to halt the pipeline and keep
     * the current response as-is. Returning any other type throws an exception.
     * </p>
     */
    abstract public function after(RequestInterface $request, ResponseInterface $response, array $arguments = []): ?ResponseInterface;

}
