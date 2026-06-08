<?php

declare(strict_types=1);

namespace Tests\Router\Unit\Resolver;

use InitPHP\HTTP\Message\Request;
use InitPHP\HTTP\Message\Response;
use InitPHP\HTTP\Message\Stream;
use InitPHP\Router\Exception\RouterException;
use InitPHP\Router\Resolver\ParameterResolver;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;
use ReflectionFunction;

require_once __DIR__ . '/ParameterResolverFixtures.php';

final class ParameterResolverTest extends TestCase
{
    private Request $request;
    private Response $response;

    protected function setUp(): void
    {
        $this->request = new Request('GET', 'http://localhost/', [], null, '1.1');
        $this->response = new Response(200, [], new Stream('', null), '1.1');
    }

    private function resolver(?ContainerInterface $container = null, bool $newInstance = false): ParameterResolver
    {
        return new ParameterResolver($this->request, $this->response, $newInstance, $container);
    }

    public function testFillsBuiltinParametersFromPositionalArguments(): void
    {
        $fn = new ReflectionFunction(static fn ($id, $slug) => null);

        $this->assertSame(['5', 'admin'], $this->resolver()->forReflection($fn, ['5', 'admin']));
    }

    public function testInjectsSharedRequestAndResponseByType(): void
    {
        $fn = new ReflectionFunction(static fn (RequestInterface $req, ResponseInterface $res) => null);

        $args = $this->resolver()->forReflection($fn, []);

        $this->assertSame($this->request, $args[0]);
        $this->assertSame($this->response, $args[1]);
    }

    public function testUsesDefaultValueWhenNoArgumentProvided(): void
    {
        $fn = new ReflectionFunction(static fn ($page = 1) => null);

        $this->assertSame([1], $this->resolver()->forReflection($fn, []));
    }

    public function testUsesNullForNullableParameterWithoutArgument(): void
    {
        $fn = new ReflectionFunction(static fn (?string $q) => null);

        $this->assertSame([null], $this->resolver()->forReflection($fn, []));
    }

    public function testThrowsForRequiredBuiltinParameterThatCannotBeFilled(): void
    {
        // B5 regression: previously the parameter was silently skipped, which
        // shifted positional arguments and caused an ArgumentCountError later.
        $fn = new ReflectionFunction(static fn (string $required) => null);

        $this->expectException(RouterException::class);
        $this->resolver()->forReflection($fn, []);
    }

    public function testMakeReturnsSharedRequestForRequestTypedClass(): void
    {
        $made = $this->resolver()->make(new ReflectionClass($this->request));

        $this->assertSame($this->request, $made);
    }

    public function testMakeInstantiatesAndResolvesConstructorDependencies(): void
    {
        $service = $this->resolver()->make(new ReflectionClass(DependentService::class));

        $this->assertInstanceOf(DependentService::class, $service);
        $this->assertInstanceOf(PlainService::class, $service->dependency);
    }

    public function testMakePrefersContainerService(): void
    {
        $sentinel = new PlainService();
        $container = new class ($sentinel) implements ContainerInterface {
            public function __construct(private PlainService $service)
            {
            }

            public function get(string $id): mixed
            {
                return $this->service;
            }

            public function has(string $id): bool
            {
                return true;
            }
        };

        $made = $this->resolver($container)->make(new ReflectionClass(PlainService::class));

        $this->assertSame($sentinel, $made);
    }
}
