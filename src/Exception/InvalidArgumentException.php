<?php

/**
 * InvalidArgumentException.php
 *
 * This file is part of Router.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://muhammetsafak.com
 */

declare(strict_types=1);

namespace InitPHP\Router\Exception;

/**
 * Thrown when a router method receives an argument of an invalid type or value
 * (an unsupported HTTP method, a bad handler type, an invalid IP address, ...).
 */
class InvalidArgumentException extends \InvalidArgumentException {}
