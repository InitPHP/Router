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

final class RichMiddlewareController
{
    /** @var array<int|string, mixed> */
    public array $middlewares = [
        'GlobalFilter',
        'before' => ['GlobalBefore'],
        'after'  => ['GlobalAfter'],
        'index'  => [
            'before' => ['IndexBefore'],
            'after'  => ['IndexAfter'],
        ],
    ];

    public function index(): string
    {
        return 'rich';
    }
}

final class GreetingService
{
    public function greet(): string
    {
        return 'hello-from-service';
    }
}

final class ServiceController
{
    public function __construct(private GreetingService $service)
    {
    }

    public function index(): string
    {
        return $this->service->greet();
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
