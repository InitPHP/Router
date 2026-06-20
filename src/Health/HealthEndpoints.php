<?php

/**
 * HealthEndpoints.php
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

use InitPHP\Router\Router;
use Psr\Http\Message\ResponseInterface;

/**
 * Registers first-class liveness and readiness HTTP endpoints on a {@see Router}.
 *
 * - **Liveness** (`/healthz` by default): proves the process is up and able to
 *   serve. It runs no dependency checks and always returns 200 — reaching the
 *   handler is itself the signal. Use it for "restart me if this fails" probes.
 * - **Readiness** (`/readyz` by default): runs the {@see HealthRegistry} and
 *   returns 200 when every dependency check passes, or 503 when any fails. Use
 *   it for "route traffic to me only when this passes" probes.
 *
 * Both responses carry a JSON body. The routes are tagged with a `health`
 * option (`'liveness'` / `'readiness'`) so {@see ReadinessLinter} can recognise
 * them regardless of the chosen paths.
 */
final class HealthEndpoints
{
    /** Route option key used to tag a health endpoint. */
    public const OPTION_KEY = 'health';

    /** Tag value for the liveness endpoint. */
    public const KIND_LIVENESS = 'liveness';

    /** Tag value for the readiness endpoint. */
    public const KIND_READINESS = 'readiness';

    public const DEFAULT_LIVENESS_PATH = '/healthz';

    public const DEFAULT_READINESS_PATH = '/readyz';

    public function __construct(private HealthRegistry $registry)
    {
    }

    /**
     * The readiness-check registry backing the `/readyz` endpoint. Register your
     * db-ping/cache-ping checks on it before (or after) registering the routes.
     */
    public function registry(): HealthRegistry
    {
        return $this->registry;
    }

    /**
     * Register both the liveness and readiness routes on the given router.
     *
     * @param Router $router
     * @param string $livenessPath  Path for the liveness probe.
     * @param string $readinessPath Path for the readiness probe.
     * @return $this
     */
    public function register(
        Router $router,
        string $livenessPath = self::DEFAULT_LIVENESS_PATH,
        string $readinessPath = self::DEFAULT_READINESS_PATH
    ): self {
        $this->registerLiveness($router, $livenessPath);
        $this->registerReadiness($router, $readinessPath);

        return $this;
    }

    /**
     * Register only the liveness (`/healthz`-style) route: always 200 + a tiny
     * JSON body. The route is GET (and HEAD, so probes that issue HEAD work too).
     *
     * @return $this
     */
    public function registerLiveness(Router $router, string $path = self::DEFAULT_LIVENESS_PATH): self
    {
        $router->register(
            ['GET', 'HEAD'],
            $path,
            function (ResponseInterface $response): ResponseInterface {
                return $this->json($response, 200, ['status' => 'ok']);
            },
            [self::OPTION_KEY => self::KIND_LIVENESS]
        );

        return $this;
    }

    /**
     * Register only the readiness (`/readyz`-style) route: runs the registry and
     * returns 200 when all checks pass, 503 otherwise, with a JSON body listing
     * each check's status.
     *
     * @return $this
     */
    public function registerReadiness(Router $router, string $path = self::DEFAULT_READINESS_PATH): self
    {
        $router->register(
            ['GET', 'HEAD'],
            $path,
            function (ResponseInterface $response): ResponseInterface {
                $report = $this->registry->run();

                return $this->json($response, $report->statusCode(), $report->toArray());
            },
            [self::OPTION_KEY => self::KIND_READINESS]
        );

        return $this;
    }

    /**
     * Build a JSON response from $payload with the given status, staying within
     * the PSR-7 contract (no dependency on a concrete message implementation).
     * Falls back gracefully when the body stream is not writable.
     *
     * @param array<string, mixed> $payload
     */
    private function json(ResponseInterface $response, int $status, array $payload): ResponseInterface
    {
        $response = $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store');

        $body = $response->getBody();
        if ($body->isWritable()) {
            if ($body->isSeekable()) {
                $body->rewind();
            }
            $body->write((string) \json_encode($payload, \JSON_UNESCAPED_SLASHES));
        }

        return $response;
    }
}
