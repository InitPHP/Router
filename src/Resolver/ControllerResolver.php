<?php

/**
 * ControllerResolver.php
 *
 * This file is part of Router.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Router\Resolver;

use InitPHP\Router\Exception\RouterException;

/**
 * Splits route handlers into a controller/method pair and resolves a
 * controller reference to a loadable class name.
 */
final class ControllerResolver
{
    public function __construct(
        private ?string $namespace = null,
        private ?string $path = null
    ) {
    }

    /**
     * Splits a non-closure handler into its controller and method parts.
     *
     * Accepts "Controller@method", "Controller::method", "Controller->method",
     * a [class, method] list, or a [class => method] map.
     *
     * @param string|array<int|string, mixed> $execute
     * @return array{controller: string|object, method: string}
     */
    public function parse(string|array $execute): array
    {
        if (\is_array($execute)) {
            if (\array_is_list($execute)) {
                return ['controller' => $execute[0], 'method' => (string) $execute[1]];
            }

            return ['controller' => (string) \key($execute), 'method' => (string) \current($execute)];
        }

        foreach (['::', '@', '->'] as $separator) {
            if (\str_contains($execute, $separator)) {
                $split = \explode($separator, $execute, 2);

                return ['controller' => $split[0], 'method' => $split[1]];
            }
        }

        throw new RouterException('The requested controller and method are not understood.');
    }

    /**
     * Resolves a controller reference to an existing, loadable class name.
     *
     * Objects are returned as-is. A bare class name is resolved against the
     * configured namespace and, if needed, required from the configured path.
     *
     * @param string|object $class
     * @return string|object
     * @throws RouterException When the controller cannot be located.
     */
    public function find(string|object $class): string|object
    {
        if (\is_object($class)) {
            return $class;
        }
        if (\class_exists($class)) {
            return $class;
        }
        if (empty($this->namespace)) {
            throw new RouterException('"' . $class . '" controller not found.');
        }

        $controller = \rtrim($this->namespace, '\\') . '\\' . \ltrim($class, '\\');
        if (\class_exists($controller)) {
            return $controller;
        }
        if (!empty($this->path)) {
            $file = \rtrim($this->path, '\\/') . \DIRECTORY_SEPARATOR . $class . '.php';
            if (\is_file($file)) {
                require $file;
                if (\class_exists($controller)) {
                    return $controller;
                }
            }
        }

        throw new RouterException('"' . $controller . '" controller not found.');
    }
}
