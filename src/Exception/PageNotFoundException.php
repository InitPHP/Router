<?php

/**
 * PageNotFoundException.php
 *
 * This file is part of Router.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Router\Exception;

/**
 * Thrown by dispatch() when no route matches the request and no 404 handler
 * has been defined.
 */
class PageNotFoundException extends RouterException
{
}
