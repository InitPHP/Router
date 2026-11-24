<?php

/**
 * RequestContext.php
 *
 * This file is part of Router.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://muhammetsafak.com
 */

declare(strict_types=1);

namespace InitPHP\Router\Http;

use InitPHP\Router\Exception\InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Derives the request facts the router routes on — the (optionally overridden)
 * HTTP method and the client IP address — from the PSR-7 request and the
 * server/request parameter bags.
 *
 * Client IP: the address comes from `REMOTE_ADDR`. The `X-Forwarded-For` header
 * is read only when `REMOTE_ADDR` is one of the configured trusted proxies; the
 * chain is then resolved from right to left, skipping trusted proxies, and the
 * first untrusted address is the client. `Client-IP` is never read.
 *
 * Method override: `_method` is honoured only when the real method is `POST`,
 * and only from the POST parameters (the parsed body on a PSR-7 server request,
 * `$_POST` otherwise) — never from the query string.
 *
 * With a PSR-7 server request the request is the source of truth: its server
 * parameters (when it carries any), its `X-Forwarded-For` header lines and its
 * parsed body are used instead of the superglobals. Server parameters of some
 * runtimes (ReactPHP) carry no `HTTP_*` entries, so the header is read from the
 * request itself; the `HTTP_X_FORWARDED_FOR` server parameter is the fallback.
 *
 * The parameter bags are injectable so the detection logic is testable without
 * touching superglobals.
 */
final class RequestContext
{
    private string $method;

    private ?string $clientIp;

    /**
     * @var list<array{0: string, 1: int}> Trusted proxies as packed network address and prefix length in bits.
     */
    private array $trustedProxies = [];

    /**
     * @param list<string> $supportedMethods Methods accepted for "_method" override.
     * @param array<string, mixed>|null $serverParams Defaults to the server parameters of a PSR-7
     * server request when it has any, otherwise to $_SERVER.
     * @param array<string, mixed>|null $requestParams POST parameters read for the "_method" override.
     * Defaults to the parsed body of a PSR-7 server request (an array or an object; none when it is
     * null), otherwise to $_POST.
     * @param list<mixed> $trustedProxies IP addresses or CIDR ranges (IPv4 or IPv6) of the proxies
     * whose X-Forwarded-For header may be trusted, as strings; any other value is rejected.
     * Empty by default: the header is ignored.
     * @throws InvalidArgumentException When a trusted proxy entry is not a valid IP address or CIDR range.
     */
    public function __construct(
        RequestInterface $request,
        bool $variableMethod = false,
        array $supportedMethods = [],
        ?array $serverParams = null,
        ?array $requestParams = null,
        array $trustedProxies = [],
    ) {
        $forwardedFor = null;
        if ($request instanceof ServerRequestInterface) {
            $serverParams ??= $request->getServerParams() !== [] ? $request->getServerParams() : $_SERVER;
            // Several header lines keep their order: the proxy nearest to us appended last.
            $lines = $request->getHeader('X-Forwarded-For');
            $forwardedFor = $lines === [] ? null : \implode(',', $lines);
            $parsedBody = $request->getParsedBody();
            $requestParams ??= \is_array($parsedBody) ? $parsedBody : (\is_object($parsedBody) ? (array) $parsedBody : []);
        } else {
            $serverParams ??= $_SERVER;
            $requestParams ??= $_POST;
        }

        foreach ($trustedProxies as $proxy) {
            $this->trustedProxies[] = $this->parseTrustedProxy($proxy);
        }

        $this->method = $this->detectMethod($request, $variableMethod, $supportedMethods, $requestParams);
        $forwardedFor ??= $serverParams['HTTP_X_FORWARDED_FOR'] ?? null;
        $this->clientIp = $this->detectClientIp($serverParams, $forwardedFor);
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
     * @param array<array-key, mixed> $requestParams
     */
    private function detectMethod(
        RequestInterface $request,
        bool $variableMethod,
        array $supportedMethods,
        array $requestParams,
    ): string {
        $method = \strtoupper($request->getMethod());
        if (!$variableMethod || $method !== 'POST' || !isset($requestParams['_method']) || !\is_string($requestParams['_method'])) {
            return $method;
        }
        $override = \strtoupper($requestParams['_method']);

        return \in_array($override, $supportedMethods, true) ? $override : $method;
    }

    /**
     * @param array<string, mixed> $serverParams
     */
    private function detectClientIp(array $serverParams, mixed $forwarded): ?string
    {
        $remote = \is_string($serverParams['REMOTE_ADDR'] ?? null) ? self::toPacked($serverParams['REMOTE_ADDR']) : null;
        if ($remote === null) {
            return null;
        }
        if ($this->trustedProxies === [] || !$this->isTrusted($remote) || !\is_string($forwarded) || \trim($forwarded) === '') {
            return self::toText($remote);
        }

        // Right to left: every hop we trust vouches for the address to its left;
        // the first address we do not trust is the client. An entry that is not
        // an IP address breaks the chain, so no client address is reported.
        $client = $remote;
        $chain = \explode(',', $forwarded);
        for ($i = \count($chain) - 1; $i >= 0; --$i) {
            $hop = self::toPacked($chain[$i]);
            if ($hop === null) {
                return null;
            }
            $client = $hop;
            if (!$this->isTrusted($hop)) {
                break;
            }
        }

        return self::toText($client);
    }

    /**
     * The canonical text form of an IP address (IPv4-mapped IPv6 collapsed to
     * IPv4, IPv6 in its short lower-case form); null when the value is not an IP
     * address. Client addresses and `ip()` allow-list entries are compared in
     * this form.
     */
    public static function normalizeIp(string $ip): ?string
    {
        $packed = self::toPacked($ip);

        return $packed === null ? null : self::toText($packed);
    }

    private function isTrusted(string $packed): bool
    {
        foreach ($this->trustedProxies as [$network, $prefix]) {
            if (\strlen($network) !== \strlen($packed)) {
                continue;
            }
            $bytes = \intdiv($prefix, 8);
            if (\substr($network, 0, $bytes) !== \substr($packed, 0, $bytes)) {
                continue;
            }
            $bits = $prefix % 8;
            if ($bits === 0) {
                return true;
            }
            $mask = (0xFF << (8 - $bits)) & 0xFF;
            if ((\ord($network[$bytes]) & $mask) === (\ord($packed[$bytes]) & $mask)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function parseTrustedProxy(mixed $proxy): array
    {
        $address = \is_string($proxy) ? $proxy : '';
        $prefix = null;
        if (\str_contains($address, '/')) {
            [$address, $length] = \explode('/', $address, 2);
            if ($length === '' || !\ctype_digit($length) || \strlen($length) > 3) {
                throw new InvalidArgumentException('Invalid trusted proxy: the CIDR prefix length must be a number.');
            }
            $prefix = (int) $length;
        }
        $address = \trim($address);
        $raw = \filter_var($address, \FILTER_VALIDATE_IP) === false ? false : \inet_pton($address);
        if ($raw === false) {
            throw new InvalidArgumentException(
                'Invalid trusted proxy: it must be an IP address or a CIDR range such as "10.0.0.0/8".',
            );
        }
        if ($prefix !== null && self::isMapped($raw)) {
            // "::ffff:10.0.0.0/104" is the IPv4 range 10.0.0.0/8.
            $prefix = $prefix >= 96 ? $prefix - 96 : -1;
        }
        $packed = self::collapse($raw);
        $prefix ??= \strlen($packed) * 8;
        if ($prefix < 0 || $prefix > \strlen($packed) * 8) {
            throw new InvalidArgumentException('Invalid trusted proxy: the CIDR prefix length is out of range.');
        }

        return [$packed, $prefix];
    }

    /**
     * An IP address as 4 (IPv4) or 16 (IPv6) bytes, with IPv4-mapped IPv6
     * addresses collapsed to IPv4; null when the value is not an IP address.
     */
    private static function toPacked(string $ip): ?string
    {
        $ip = \trim($ip);
        if (\filter_var($ip, \FILTER_VALIDATE_IP) === false) {
            return null;
        }
        $raw = \inet_pton($ip);

        return $raw === false ? null : self::collapse($raw);
    }

    private static function toText(string $packed): ?string
    {
        $text = \inet_ntop($packed);

        return $text === false ? null : $text;
    }

    private static function isMapped(string $raw): bool
    {
        return \strlen($raw) === 16 && \str_starts_with($raw, "\0\0\0\0\0\0\0\0\0\0\xff\xff");
    }

    private static function collapse(string $raw): string
    {
        return self::isMapped($raw) ? \substr($raw, 12) : $raw;
    }
}
