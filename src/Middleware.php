<?php
/**
 * Middleware.php
 *
 * This file is part of InitPHP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    http://initphp.github.io/license.txt  MIT
 * @version    1.0
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Router;

use \Psr\Http\Message\{RequestInterface, ResponseInterface};

/**
 * This class defines the basic structure of a Middleware class.
 *
 * Every middleware class should extend from this class.
 *
 * @link https://github.com/initphp/router/Wiki/09.Middlewares
 */
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
     * @return ResponseInterface <p>It should return the response object to be used afterwards. Otherwise, an exception is thrown.</p>
     */
    abstract public function before(RequestInterface $request, ResponseInterface $response, array $arguments = []): ResponseInterface;

    /**
     * The method to run after the routes finish executing.
     *
     * This method works between route and response.
     *
     * @param RequestInterface $request <p>The request object used.</p>
     * @param ResponseInterface $response <p>The response object used.</p>
     * @param array $arguments <p>Array holding the route's arguments, if any.</p>
     * @return ResponseInterface <p>It should return the response object to be used afterwards. Otherwise, an exception is thrown.</p>
     */
    abstract public function after(RequestInterface $request, ResponseInterface $response, array $arguments = []): ResponseInterface;

}
