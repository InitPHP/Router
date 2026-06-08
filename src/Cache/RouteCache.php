<?php

/**
 * RouteCache.php
 *
 * This file is part of Router.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Router\Cache;

use InitPHP\Router\Exception\RouterException;

/**
 * Serializes the compiled route table to a file and reads it back within a
 * configurable TTL, so route definitions do not have to be rebuilt on every
 * request.
 */
final class RouteCache
{
    private bool $enabled;

    private ?string $path;

    private int $ttl;

    /**
     * @param array<string, mixed> $config Keys: enable (bool), path (string), ttl (int seconds).
     */
    public function __construct(array $config = [])
    {
        $this->enabled = (bool) ($config['enable'] ?? false);
        $this->path = (isset($config['path']) && \is_string($config['path']) && $config['path'] !== '')
            ? $config['path']
            : null;
        $this->ttl = (int) ($config['ttl'] ?? 86400);
    }

    public function isEnabled(): bool
    {
        return $this->enabled && $this->path !== null;
    }

    /**
     * Loads the cached route state, or null when caching is disabled, the file
     * is missing/unreadable as a cache entry, or the entry has expired.
     *
     * @return array<string, mixed>|null
     * @throws RouterException When the cache file exists but cannot be read.
     */
    public function load(): ?array
    {
        if (!$this->enabled || $this->path === null || !\is_file($this->path)) {
            return null;
        }
        if (($read = @\file_get_contents($this->path)) === false) {
            throw new RouterException('Cannot read router cache file (' . $this->path . ')');
        }

        $data = @\unserialize($read, ['allowed_classes' => false]);
        if (!\is_array($data) || !isset($data['created_at'], $data['data']) || !\is_array($data['data'])) {
            return null;
        }
        if (((int) $data['created_at'] + $this->ttl) < \time()) {
            return null;
        }

        return $data['data'];
    }

    /**
     * Persists the given route state.
     *
     * Never throws — it may run during object destruction, where exceptions are
     * fatal. A failed write emits an E_USER_WARNING and returns false.
     *
     * @param array<string, mixed> $state
     */
    public function save(array $state): bool
    {
        if (!$this->enabled || $this->path === null) {
            return false;
        }

        $payload = \serialize(['created_at' => \time(), 'data' => $state]);
        if (@\file_put_contents($this->path, $payload) === false) {
            \trigger_error('Failed to create router cache file (' . $this->path . ').', \E_USER_WARNING);

            return false;
        }

        return true;
    }
}
