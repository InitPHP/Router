<?php

/**
 * LintSeverity.php
 *
 * This file is part of Router.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Router\Health;

/**
 * Severity of a {@see LintFinding}. `ERROR` fails the readiness gate; `WARNING`
 * is advisory (surfaced but does not, on its own, fail CI).
 */
enum LintSeverity: string
{
    case ERROR = 'error';
    case WARNING = 'warning';
}
