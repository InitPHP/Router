<?php

/**
 * RequestContext.php
 *
 * This file is part of Router.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Router\Http;

use Psr\Http\Message\RequestInterface;

/**
 * Derives the request facts the router routes on — the (optionally overridden)
 * HTTP method and the client IP address — from the PSR-7 request and the
 * server/request parameter bags.
 *
 * The parameter bags are injectable so the detection logic is testable without
 * touching superglobals.
 */
final class RequestContext
{
    private string $method;

    private ?string $clientIp;

    /**
     * @param list<string> $supportedMethods Methods accepted for "_method" override.
     * @param array<string, mixed>|null $serverParams Defaults to $_SERVER.
     * @param array<string, mixed>|null $requestParams Defaults to $_REQUEST.
     */
    public function __construct(
        RequestInterface $request,
        bool $variableMethod = false,
        array $supportedMethods = [],
        ?array $serverParams = null,
        ?array $requestParams = null
    ) {
        $serverParams ??= $_SERVER;
        $requestParams ??= $_REQUEST;

        $this->method = $this->detectMethod($request, $variableMethod, $supportedMethods, $requestParams);
        $this->clientIp = $this->detectClientIp($serverParams);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getClientIp(): ?string
    {
        return $this->clientIp;
    }

    /**
     * @param list<string> $supportedMethods
     * @param array<string, mixed> $requestParams
     */
    private function detectMethod(
        RequestInterface $request,
        bool $variableMethod,
        array $supportedMethods,
        array $requestParams
    ): string {
        $method = \strtoupper($request->getMethod());
        if (!$variableMethod || !isset($requestParams['_method'])) {
            return $method;
        }
        $override = \strtoupper((string) $requestParams['_method']);

        return \in_array($override, $supportedMethods, true) ? $override : $method;
    }

    /**
     * @param array<string, mixed> $serverParams
     */
    private function detectClientIp(array $serverParams): ?string
    {
        if (!empty($serverParams['HTTP_CLIENT_IP'])) {
            return (string) $serverParams['HTTP_CLIENT_IP'];
        }
        if (!empty($serverParams['HTTP_X_FORWARDED_FOR'])) {
            $forwarded = (string) $serverParams['HTTP_X_FORWARDED_FOR'];
            if (\str_contains($forwarded, ',')) {
                $forwarded = \trim(\explode(',', $forwarded, 2)[0]);
            }

            return $forwarded;
        }

        return isset($serverParams['REMOTE_ADDR']) ? (string) $serverParams['REMOTE_ADDR'] : null;
    }
}
