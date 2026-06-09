<?php

/**
 * RouterException.php
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
 * Base exception for all router runtime errors (unresolved controllers or
 * middleware, duplicate route names, invalid filters, cache I/O, etc.).
 */
class RouterException extends \RuntimeException
{
}
