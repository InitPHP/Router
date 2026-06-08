<?php

/**
 * FileLinkHandler.php
 *
 * This file is part of Router.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Router\Link;

use Psr\Http\Message\ResponseInterface;

/**
 * Resolves and serves the files behind LINK routes.
 *
 * Directory links are protected against path traversal: the resolved real
 * path must stay inside the link root. Files are streamed into the PSR-7
 * response body in chunks so large files do not require loading the whole
 * file into memory and never bypass the response pipeline.
 */
final class FileLinkHandler
{
    private const CHUNK_SIZE = 8192;

    /**
     * Resolves the real file a link should serve, or null when there is
     * nothing safe to serve (missing file, or a directory-link request that
     * escapes the link root).
     *
     * @param array<int, mixed> $arguments The matched route arguments.
     */
    public function resolve(string $source, array $arguments): ?string
    {
        if (\is_dir($source)) {
            if (($root = \realpath($source)) === false) {
                return null;
            }
            $suffix = isset($arguments[0]) ? \ltrim((string) $arguments[0], '/\\') : '';
            $target = \realpath($root . \DIRECTORY_SEPARATOR . $suffix);
            if ($target === false || !\is_file($target)) {
                return null;
            }
            if ($target !== $root && \strncmp($target, $root . \DIRECTORY_SEPARATOR, \strlen($root) + 1) !== 0) {
                return null;
            }

            return $target;
        }

        return \is_file($source) ? $source : null;
    }

    /**
     * Streams the linked file into the response, returning the new response,
     * or null when there is nothing safe to serve.
     *
     * @param array<int, mixed> $arguments
     */
    public function serve(string $source, array $arguments, ResponseInterface $response): ?ResponseInterface
    {
        $path = $this->resolve($source, $arguments);
        if ($path === null) {
            return null;
        }

        $size = (int) \filesize($path);
        $mime = (string) \mime_content_type($path);

        $body = $response->getBody();
        if ($body->isWritable() && ($handle = @\fopen($path, 'rb')) !== false) {
            while (!\feof($handle)) {
                $chunk = \fread($handle, self::CHUNK_SIZE);
                if ($chunk === false) {
                    break;
                }
                $body->write($chunk);
            }
            \fclose($handle);
        }

        return $response
            ->withHeader('Content-Type', $mime)
            ->withHeader('Content-Length', (string) $size);
    }
}
