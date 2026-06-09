<?php

/**
 * RouteCollection.php
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
 * Stores the registered routes and the indexes used to look them up: by id, by
 * HTTP method, and by name. Also serialises itself to/from the route cache.
 */
final class RouteCollection
{
    /** @var array<int, Route> */
    private array $routes = [];

    /** @var array<string, list<int>> */
    private array $methodIds = [];

    /** @var array<string, int> */
    private array $names = [];

    private int $lastId = 0;

    /**
     * @param list<string> $supportedMethods
     */
    public function __construct(array $supportedMethods)
    {
        foreach ($supportedMethods as $method) {
            $this->methodIds[$method] = [];
        }
    }

    public function nextId(): int
    {
        return ++$this->lastId;
    }

    public function add(Route $route): void
    {
        $this->routes[$route->getId()] = $route;
        foreach ($route->getMethods() as $method) {
            $this->methodIds[$method][] = $route->getId();
        }
    }

    /**
     * The most recently added route (the one name()/filter() operate on).
     */
    public function last(): ?Route
    {
        return $this->routes[$this->lastId] ?? null;
    }

    public function get(int $id): ?Route
    {
        return $this->routes[$id] ?? null;
    }

    public function hasName(string $name): bool
    {
        return isset($this->names[$name]);
    }

    public function setName(string $name, int $id): void
    {
        $this->names[$name] = $id;
    }

    public function byName(string $name): ?Route
    {
        if (!isset($this->names[$name])) {
            return null;
        }

        return $this->routes[$this->names[$name]] ?? null;
    }

    /**
     * @return array<int, Route>
     */
    public function all(): array
    {
        return $this->routes;
    }

    /**
     * Routes registered for exactly the given method (no ANY/LINK fallback).
     *
     * @return list<Route>
     */
    public function ofMethod(string $method): array
    {
        $res = [];
        foreach ($this->methodIds[$method] ?? [] as $id) {
            if (isset($this->routes[$id])) {
                $res[] = $this->routes[$id];
            }
        }

        return $res;
    }

    /**
     * Candidate routes for matching a request method: LINK and ANY routes are
     * always included alongside the method's own routes.
     *
     * @return list<Route>
     */
    public function candidates(string $method): array
    {
        $ids = \array_unique(\array_merge(
            $this->methodIds['LINK'] ?? [],
            $this->methodIds['ANY'] ?? [],
            $this->methodIds[$method] ?? []
        ));

        $res = [];
        foreach ($ids as $id) {
            if (isset($this->routes[$id])) {
                $res[] = $this->routes[$id];
            }
        }

        return $res;
    }

    public function clear(): void
    {
        $this->routes = [];
        $this->names = [];
        $this->lastId = 0;
        foreach (\array_keys($this->methodIds) as $method) {
            $this->methodIds[$method] = [];
        }
    }

    /**
     * @return array{id: int, routes: array<int, array<string, mixed>>, names: array<string, int>, methodIds: array<string, list<int>>}
     */
    public function toCache(): array
    {
        return [
            'id'        => $this->lastId,
            'routes'    => \array_map(static fn (Route $route): array => $route->toArray(), $this->routes),
            'names'     => $this->names,
            'methodIds' => $this->methodIds,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function fromCache(array $data): void
    {
        $this->lastId = (int) ($data['id'] ?? 0);
        $this->names = \is_array($data['names'] ?? null) ? $data['names'] : [];
        $this->methodIds = \is_array($data['methodIds'] ?? null) ? $data['methodIds'] : $this->methodIds;
        $this->routes = [];
        foreach (($data['routes'] ?? []) as $id => $routeData) {
            if (\is_array($routeData)) {
                $this->routes[(int) $id] = Route::fromArray($routeData);
            }
        }
    }
}
