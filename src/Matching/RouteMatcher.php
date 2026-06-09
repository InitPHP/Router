<?php

/**
 * RouteMatcher.php
 *
 * This file is part of Router.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Router\Matching;

use InitPHP\Router\Link\FileLinkHandler;
use InitPHP\Router\Route;
use InitPHP\Router\RouteCollection;

/**
 * Selects the route that matches a request: filters by client IP, compiles each
 * candidate path into a regular expression, and — among the matches — prefers
 * the most specific one (by path length and number of captured arguments).
 *
 * A matching LINK route additionally has its target verified to exist and stay
 * inside the link root before it is accepted.
 */
final class RouteMatcher
{
    public function __construct(
        private RouteCollection $routes,
        private PatternRegistry $patterns,
        private FileLinkHandler $links
    ) {
    }

    public function match(string $method, string $url, ?string $clientIp): ?Route
    {
        $compiled = $this->patterns->compiled();
        $matches = [];

        foreach ($this->routes->candidates($method) as $route) {
            $ip = $route->getOption('ip');
            if (!empty($ip)) {
                $allowed = \is_array($ip) ? $ip : [$ip];
                if (!\in_array($clientIp, $allowed, true)) {
                    continue;
                }
            }

            $pattern = (string) \preg_replace($compiled['keys'], $compiled['values'], $route->getPath());
            if (\preg_match('#^' . $pattern . '$#', $url, $arguments) !== 1) {
                continue;
            }
            \array_shift($arguments);
            $matched = $route->withArguments($arguments);

            if ($matched->isLink()) {
                if ($this->links->resolve((string) $matched->getHandler(), $arguments) === null) {
                    // Missing file or one escaping the link root: let it 404.
                    continue;
                }

                return $matched;
            }

            $score = \strlen($route->getPath()) + (\count($arguments) * 25);
            $matches[$score][] = $matched;
        }

        if ($matches === []) {
            return null;
        }

        \krsort($matches);
        $best = \current($matches);

        return $best[0];
    }
}
