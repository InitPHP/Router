<?php

/**
 * Dispatcher.php
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

use InitPHP\Router\Exception\PageNotFoundException;
use InitPHP\Router\Link\FileLinkHandler;
use InitPHP\Router\Resolver\ControllerResolver;
use InitPHP\Router\Resolver\ParameterResolver;
use InitPHP\Router\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;

/**
 * Runs a matched route (or the 404 handler) through the full pipeline:
 * before-middleware, the handler (closure, controller method or LINK file), and
 * after-middleware, folding each step's result into the response. The current
 * response is threaded through every step so handlers observe middleware
 * modifications.
 */
final class Dispatcher
{
    private ?string $currentController = null;

    private ?string $currentControllerMethod = null;

    /** @var list<mixed> */
    private array $currentArguments = [];

    private string $content = '';

    public function __construct(
        private ControllerResolver $controllers,
        private ParameterResolver $parameters,
        private MiddlewareRunner $middleware,
        private FileLinkHandler $links
    ) {
    }

    /**
     * @param array<string, mixed> $notFound The 404 handler config (execute + options.arguments).
     * @throws PageNotFoundException When no route matched and no 404 handler is defined.
     */
    public function dispatch(?Route $route, ResponseInterface $response, array $notFound): ResponseInterface
    {
        if ($route === null) {
            return $this->notFound($response, $notFound);
        }

        $arguments = $route->getArguments();
        $this->currentArguments = $arguments;
        $options = $route->getOptions();
        $filters = $this->splitFilters($options['middleware'] ?? []);

        $before = $this->middleware->run($filters['before'], $response, $arguments, true, $options);
        $response = $before->response;
        if ($before->halted) {
            return $response;
        }

        $handler = $route->getHandler();
        if ($route->isLink()) {
            $served = $this->links->serve(\is_string($handler) ? $handler : '', $arguments, $response);

            return $served ?? $this->notFound($response, $notFound);
        }

        if (\is_callable($handler)) {
            $this->currentController = '__CALLABLE__';
            $this->currentControllerMethod = '';
            $response = $this->run($handler, $arguments, $response);
        } else {
            $parse = $this->controllers->parse($handler);
            $controller = $this->parameters->make(
                new ReflectionClass($this->controllers->find($parse['controller'])),
                $response
            );

            $controllerBefore = $this->middleware->run(
                $this->middleware->controllerMiddlewares($controller, $parse['method'], true),
                $response,
                $arguments,
                true,
                $options
            );
            $response = $controllerBefore->response;
            $this->currentController = \get_class($controller);
            $this->currentControllerMethod = $parse['method'];
            if ($controllerBefore->halted) {
                return $response;
            }

            $response = $this->run([$controller, $parse['method']], $arguments, $response);
            $filters['after'] = \array_merge(
                $filters['after'],
                $this->middleware->controllerMiddlewares($controller, $parse['method'], false)
            );
        }

        return $this->middleware->run($filters['after'], $response, $arguments, false, $options)->response;
    }

    public function getCurrentController(): ?string
    {
        return $this->currentController;
    }

    public function getCurrentControllerMethod(): ?string
    {
        return $this->currentControllerMethod;
    }

    /** @return list<mixed> */
    public function getCurrentArguments(): array
    {
        return $this->currentArguments;
    }

    public function reset(): void
    {
        $this->currentController = null;
        $this->currentControllerMethod = null;
        $this->currentArguments = [];
        $this->content = '';
    }

    /**
     * Invokes a closure or [controller, method] handler, capturing echoed
     * output and folding the return value (string, ResponseInterface or
     * StreamInterface) into the response.
     *
     * @param callable|array{0: object|string, 1: string} $handler
     * @param array<int, mixed> $arguments
     */
    private function run($handler, array $arguments, ResponseInterface $response): ResponseInterface
    {
        $reflection = \is_array($handler)
            ? new ReflectionMethod($handler[0], $handler[1])
            : new ReflectionFunction($handler);

        $this->content = '';
        \ob_start(function (string $buffer): string {
            $this->content .= $buffer;

            return '';
        });
        $result = \call_user_func_array($handler, $this->parameters->forReflection($reflection, $arguments, $response));
        \ob_end_clean();

        if ($result instanceof ResponseInterface) {
            $response = $result;
        } elseif ($result instanceof StreamInterface) {
            $response = $response->withBody($result);
        } else {
            $this->content .= (string) $result;
        }
        if ($this->content !== '' && $response->getBody()->isWritable()) {
            $response->getBody()->write($this->content);
            $this->content = '';
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $notFound
     * @throws PageNotFoundException
     */
    private function notFound(ResponseInterface $response, array $notFound): ResponseInterface
    {
        $response = $response->withStatus(404);
        if (empty($notFound['execute'])) {
            throw new PageNotFoundException('Error 404 : Page Not Found');
        }

        $arguments = [];
        if (isset($notFound['options']['arguments']) && \is_array($notFound['options']['arguments'])) {
            $arguments = $notFound['options']['arguments'];
        }
        $this->currentArguments = $arguments;
        $handler = $notFound['execute'];

        if (\is_callable($handler)) {
            $this->currentController = '__CALLABLE__';
            $this->currentControllerMethod = '';

            return $this->run($handler, $arguments, $response);
        }

        $parse = $this->controllers->parse($handler);
        $controller = $this->parameters->make(
            new ReflectionClass($this->controllers->find($parse['controller'])),
            $response
        );
        $this->currentController = \get_class($controller);
        $this->currentControllerMethod = $parse['method'];

        return $this->run([$controller, $parse['method']], $arguments, $response);
    }

    /**
     * Splits a route's `middleware` option into before/after lists. Entries
     * keyed "before"/"after" go to their bucket; remaining (numeric "both")
     * entries are prepended to both buckets.
     *
     * @param mixed $middleware
     * @return array{before: array<int, mixed>, after: array<int, mixed>}
     */
    private function splitFilters($middleware): array
    {
        $filters = ['before' => [], 'after' => []];
        if (!\is_array($middleware)) {
            return $filters;
        }
        if (isset($middleware['before']) && \is_array($middleware['before'])) {
            $filters['before'] = $middleware['before'];
        }
        if (isset($middleware['after']) && \is_array($middleware['after'])) {
            $filters['after'] = $middleware['after'];
        }
        unset($middleware['before'], $middleware['after']);
        if ($middleware !== []) {
            $filters['before'] = \array_merge($middleware, $filters['before']);
            $filters['after'] = \array_merge($middleware, $filters['after']);
        }

        return $filters;
    }
}
