<?php

/**
 * CheckResult.php
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
 * The outcome of a single readiness check: a name, an ok/fail flag and an
 * optional human-readable detail (e.g. an error message or a latency note).
 *
 * Immutable value object. Use {@see CheckResult::ok()} / {@see CheckResult::fail()}
 * for readable construction, or return a bare bool from a check callable and let
 * {@see HealthRegistry} wrap it.
 */
final class CheckResult
{
    public function __construct(
        public readonly string $name,
        public readonly bool $ok,
        public readonly ?string $detail = null
    ) {
    }

    /**
     * A passing result for $name, with an optional detail.
     */
    public static function ok(string $name, ?string $detail = null): self
    {
        return new self($name, true, $detail);
    }

    /**
     * A failing result for $name, with an optional detail describing why.
     */
    public static function fail(string $name, ?string $detail = null): self
    {
        return new self($name, false, $detail);
    }

    /**
     * The JSON-friendly shape used in the readiness response body: the status
     * string and, when present, the detail.
     *
     * @return array{status: string, detail?: string}
     */
    public function toArray(): array
    {
        $data = ['status' => $this->ok ? 'ok' : 'fail'];
        if ($this->detail !== null) {
            $data['detail'] = $this->detail;
        }

        return $data;
    }
}
