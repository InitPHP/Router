<?php
/**
 * Middleware.php
 *
 * This file is part of Router.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.1
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Router;

use \Psr\Http\Message\{RequestInterface, ResponseInterface};

abstract class Middleware
{

    /**
     * The method to run before the routes start executing.
     *
     * This method works between the request and the route.
     *
     * @param RequestInterface $request <p>The request object used.</p>
     * @param ResponseInterface $response <p>The response object used.</p>
     * @param array $arguments <p>Array holding the route's arguments, if any.</p>
     * @return ResponseInterface|null <p>
     * It should return the response object to be used afterwards. Otherwise, an exception is thrown.
     * If null is returned, the "exit" command will completely stop the script from running.
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
     * @param array $arguments <p>Array holding the route's arguments, if any.</p>
     * @return ResponseInterface|null <p>
     * It should return the response object to be used afterwards. Otherwise, an exception is thrown.
     * If null is returned, the "exit" command will completely stop the script from running.
     * </p>
     */
    abstract public function after(RequestInterface $request, ResponseInterface $response, array $arguments = []): ?ResponseInterface;

}
