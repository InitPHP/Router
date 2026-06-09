<?php

/**
 * MiddlewareRunner.php
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

use InitPHP\Router\Exception\RouterException;
use InitPHP\Router\Resolver\ParameterResolver;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;

/**
 * Runs the before/after middleware (filter) pipeline for a route.
 *
 * Filters are either callables or middleware class names (resolved against the
 * route's middleware namespace/path). Each filter must return a
 * ResponseInterface (which replaces the working response) or null. A non-2xx
 * response or a null return halts the pipeline — in v2 this is a clean stop, no
 * longer a process-level exit().
 */
final class MiddlewareRunner
{
    /** @var array<string, object> Resolved filter instances, reused across runs. */
    private array $instances = [];

    public function __construct(
        private RequestInterface $request,
        private ParameterResolver $resolver
    ) {
    }

    /**
     * @param array<int, string|callable> $filters
     * @param array<int, mixed> $arguments
     * @param array<string, mixed> $routeOptions Matched route options, used to locate class filters.
     */
    public function run(
        array $filters,
        ResponseInterface $response,
        array $arguments,
        bool $before,
        array $routeOptions = []
    ): MiddlewareResult {
        $alreadyRun = [];
        foreach ($filters as $filter) {
            if (\is_callable($filter)) {
                $label = '__CALLABLE__';
                $result = $filter($this->request, $response, $arguments);
            } else {
                $class = $this->resolveClass((string) $filter, $routeOptions);
                if (\in_array($class, $alreadyRun, true)) {
                    continue;
                }
                $alreadyRun[] = $class;
                $instance = $this->instances[$class] ??= $this->resolver->make(new ReflectionClass($class), $response);
                $method = $before ? 'before' : 'after';
                $label = $class . '::' . $method . '()';
                $result = \call_user_func([$instance, $method], $this->request, $response, $arguments);
            }

            if ($result instanceof ResponseInterface) {
                $response = $result;
                $status = (int) $result->getStatusCode();
                if ($status < 200 || $status > 299) {
                    return new MiddlewareResult($response, true);
                }
                continue;
            }
            if ($result === null) {
                return new MiddlewareResult($response, true);
            }

            throw new RouterException(\sprintf(
                'The "%s" filter must return a %s or null.',
                $label,
                ResponseInterface::class
            ));
        }

        return new MiddlewareResult($response, false);
    }

    /**
     * Reads the before/after filters declared by a controller's public
     * `$middlewares` property, flattening global, positional and per-method
     * entries for the requested position.
     *
     * @return array<int, mixed>
     */
    public function controllerMiddlewares(object $controller, string $method, bool $before): array
    {
        if (!\property_exists($controller, 'middlewares')) {
            return [];
        }
        if (!(new \ReflectionProperty($controller, 'middlewares'))->isPublic()) {
            return [];
        }
        $middlewares = $controller->middlewares;
        if (!\is_array($middlewares)) {
            return [];
        }

        $res = [];
        $opposite = $before ? 'after' : 'before';
        unset($middlewares[$opposite]);
        $current = $before ? 'before' : 'after';
        if (isset($middlewares[$current])) {
            $res = \is_array($middlewares[$current]) ? $middlewares[$current] : [$middlewares[$current]];
            unset($middlewares[$current]);
        }

        foreach ($middlewares as $key => $value) {
            if (!\is_string($key)) {
                $res[] = $value;
                continue;
            }
            if ($key !== $method || !\is_array($value)) {
                continue;
            }
            if (isset($value[$current]) && \is_array($value[$current])) {
                $res = \array_merge($res, $value[$current]);
            }
            unset($value['before'], $value['after']);
            foreach ($value as $row) {
                $res[] = $row;
            }
        }

        return $res;
    }

    /**
     * @param array<string, mixed> $routeOptions
     */
    private function resolveClass(string $class, array $routeOptions): string
    {
        if (\class_exists($class)) {
            return $class;
        }

        $namespace = $routeOptions['namespaces']['middleware'] ?? null;
        if (!\is_string($namespace) || $namespace === '') {
            throw new RouterException('"' . $class . '" filter/middleware class not found.');
        }
        $fullName = \rtrim($namespace, '\\') . '\\' . \ltrim($class, '\\');
        if (\class_exists($fullName)) {
            return $fullName;
        }

        $path = $routeOptions['paths']['middleware'] ?? null;
        if (\is_string($path) && $path !== '') {
            $file = \rtrim($path, '/\\') . \DIRECTORY_SEPARATOR . \ltrim($class, '\\/') . '.php';
            if (\is_file($file)) {
                require $file;
                if (\class_exists($fullName)) {
                    return $fullName;
                }
            }
        }

        throw new RouterException('"' . $class . '" filter/middleware class not found.');
    }
}
