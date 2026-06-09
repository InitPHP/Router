<?php

/**
 * MiddlewareResult.php
 *
 * This file is part of Router.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Router\Dispatch;

use Psr\Http\Message\ResponseInterface;

/**
 * The outcome of running a middleware/filter pipeline: the (possibly replaced)
 * response and whether the pipeline asked to stop early.
 */
final class MiddlewareResult
{
    public function __construct(
        public readonly ResponseInterface $response,
        public readonly bool $halted
    ) {
    }
}
