<?php

/**
 * Route.php
 *
 * This file is part of Router.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Router;

/**
 * A single registered route: its id, HTTP methods, compiled path, handler and
 * options. Matching produces a copy carrying the captured arguments.
 */
final class Route
{
    /** @var list<string> */
    private array $methods;

    /** @var string|callable|array<int|string, mixed> */
    private $handler;

    /** @var array<string, mixed> */
    private array $options;

    /** @var list<mixed> */
    private array $arguments;

    /**
     * @param list<string> $methods
     * @param string|callable|array<int|string, mixed> $handler
     * @param array<string, mixed> $options
     * @param list<mixed> $arguments
     */
    public function __construct(
        private int $id,
        array $methods,
        private string $path,
        $handler,
        array $options = [],
        array $arguments = []
    ) {
        $this->methods = \array_values($methods);
        $this->handler = $handler;
        $this->options = $options;
        $this->arguments = \array_values($arguments);
    }

    public function getId(): int
    {
        return $this->id;
    }

    /** @return list<string> */
    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /** @return string|callable|array<int|string, mixed> */
    public function getHandler()
    {
        return $this->handler;
    }

    /** @return array<string, mixed> */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public function getOption(string $key, $default = null)
    {
        return $this->options[$key] ?? $default;
    }

    /** @return list<mixed> */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function isLink(): bool
    {
        return $this->methods === ['LINK'];
    }

    /**
     * Appends a filter to the route's middleware option.
     *
     * @param 'before'|'after'|'both' $bucket
     * @param string|callable $filter
     */
    public function addMiddleware(string $bucket, $filter): void
    {
        if (!isset($this->options['middleware']) || !\is_array($this->options['middleware'])) {
            $this->options['middleware'] = [];
        }
        if ($bucket === 'both') {
            $this->options['middleware'][] = $filter;

            return;
        }
        if (!isset($this->options['middleware'][$bucket]) || !\is_array($this->options['middleware'][$bucket])) {
            $this->options['middleware'][$bucket] = [];
        }
        $this->options['middleware'][$bucket][] = $filter;
    }

    /**
     * Returns a copy of this route carrying the given matched arguments.
     *
     * @param list<mixed> $arguments
     */
    public function withArguments(array $arguments): self
    {
        $clone = clone $this;
        $clone->arguments = \array_values($arguments);

        return $clone;
    }

    /**
     * @return array{id: int, methods: list<string>, path: string, execute: mixed, options: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'id'      => $this->id,
            'methods' => $this->methods,
            'path'    => $this->path,
            'execute' => $this->handler,
            'options' => $this->options,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['id'] ?? 0),
            \is_array($data['methods'] ?? null) ? $data['methods'] : [],
            (string) ($data['path'] ?? ''),
            $data['execute'] ?? null,
            \is_array($data['options'] ?? null) ? $data['options'] : []
        );
    }
}
