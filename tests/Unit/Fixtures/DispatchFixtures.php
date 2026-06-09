<?php

declare(strict_types=1);

namespace Tests\Router\Unit\Fixtures;

use InitPHP\Router\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class DispatchController
{
    public function index(): string
    {
        return 'controller-index';
    }

    public function show(string $id): string
    {
        return 'show:' . $id;
    }
}

final class MiddlewareController
{
    /** @var array<string, mixed> */
    public array $middlewares = ['before' => [HeaderMiddleware::class]];

    public function index(): string
    {
        return 'mw-controller';
    }
}

final class HeaderMiddleware extends Middleware
{
    public function before(RequestInterface $request, ResponseInterface $response, array $arguments = []): ?ResponseInterface
    {
        return $response->withHeader('X-Before', 'ran');
    }

    public function after(RequestInterface $request, ResponseInterface $response, array $arguments = []): ?ResponseInterface
    {
        return $response->withHeader('X-After', 'ran');
    }
}

final class UnauthorizedMiddleware extends Middleware
{
    public function before(RequestInterface $request, ResponseInterface $response, array $arguments = []): ?ResponseInterface
    {
        return $response->withStatus(401);
    }

    public function after(RequestInterface $request, ResponseInterface $response, array $arguments = []): ?ResponseInterface
    {
        return $response;
    }
}
