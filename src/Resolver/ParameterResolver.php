<?php

/**
 * ParameterResolver.php
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
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;
use ReflectionFunctionAbstract;
use ReflectionNamedType;

/**
 * Builds the argument list for a handler from its reflection, injecting the
 * shared request/response, container services and the positional route
 * arguments, and instantiates classes (recursively resolving constructor
 * dependencies) when no container is available.
 */
final class ParameterResolver
{
    public function __construct(
        private RequestInterface $request,
        private bool $argumentNewInstance = false,
        private ?ContainerInterface $container = null
    ) {
    }

    /**
     * Resolves the arguments for a function or method.
     *
     * Class-typed parameters are injected (shared request/response, then
     * container/instantiation); built-in parameters are filled from the
     * positional route arguments, then a default value, then null. The current
     * response is passed in so handlers see middleware modifications.
     *
     * @param array<int, mixed> $parameters Positional route arguments.
     * @return array<int, mixed>
     * @throws RouterException When a required built-in parameter cannot be filled.
     */
    public function forReflection(ReflectionFunctionAbstract $reflector, array $parameters, ResponseInterface $response): array
    {
        $arguments = [];
        $position = 0;
        foreach ($reflector->getParameters() as $parameter) {
            $type = $parameter->getType();
            $class = ($type instanceof ReflectionNamedType && !$type->isBuiltin())
                ? new ReflectionClass($type->getName())
                : null;

            if ($class !== null) {
                if (!$this->argumentNewInstance && $class->isInstance($this->request)) {
                    $arguments[] = $this->request;
                    continue;
                }
                if (!$this->argumentNewInstance && $class->isInstance($response)) {
                    $arguments[] = $response;
                    continue;
                }
                if ($class->isInstantiable()) {
                    $arguments[] = $this->make($class, $response);
                }
                continue;
            }

            if (isset($parameters[$position])) {
                $arguments[] = $parameters[$position];
                ++$position;
                continue;
            }
            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                ++$position;
                continue;
            }
            if ($parameter->allowsNull()) {
                $arguments[] = null;
                ++$position;
                continue;
            }

            throw new RouterException(\sprintf('Unable to resolve parameter "%s".', $parameter->getName()));
        }

        return $arguments;
    }

    /**
     * Instantiates a class, reusing the shared request/response and a PSR-11
     * container when available, otherwise resolving its constructor recursively.
     *
     * @param ReflectionClass<object> $class
     * @throws RouterException When a constructor dependency cannot be resolved.
     */
    public function make(ReflectionClass $class, ResponseInterface $response): object
    {
        if (!$this->argumentNewInstance) {
            if ($class->isInstance($this->request)) {
                return $this->request;
            }
            if ($class->isInstance($response)) {
                return $response;
            }
        }
        if ($this->container !== null) {
            $service = $this->container->get($class->getName());
            if (\is_object($service)) {
                return $service;
            }
        }

        $constructor = $class->getConstructor();
        if ($constructor === null || $constructor->getParameters() === []) {
            return $class->newInstance();
        }

        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }
            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $arguments[] = $this->make(new ReflectionClass($type->getName()), $response);
                continue;
            }
            if ($parameter->allowsNull()) {
                $arguments[] = null;
                continue;
            }

            throw new RouterException(\sprintf(
                'Unable to resolve parameter "%s" of class "%s".',
                $parameter->getName(),
                $class->getName()
            ));
        }

        return $class->newInstanceArgs($arguments);
    }
}
