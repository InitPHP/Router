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

use Psr\Http\Message\{RequestInterface, ResponseInterface};

abstract class Middleware
{

    abstract public function before(RequestInterface $request, ResponseInterface $response, array $arguments = []): ResponseInterface;

    abstract public function after(ResponseInterface $request, ResponseInterface $response, array $arguments = []): ResponseInterface;

}
