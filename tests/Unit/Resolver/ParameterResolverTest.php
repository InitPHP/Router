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
        return new ParameterResolver($this->request, $newInstance, $container);
    }

    public function testFillsBuiltinParametersFromPositionalArguments(): void
    {
        $fn = new ReflectionFunction(static fn ($id, $slug) => null);

        $this->assertSame(['5', 'admin'], $this->resolver()->forReflection($fn, ['5', 'admin'], $this->response));
    }

    public function testInjectsSharedRequestAndResponseByType(): void
    {
        $fn = new ReflectionFunction(static fn (RequestInterface $req, ResponseInterface $res) => null);

        $args = $this->resolver()->forReflection($fn, [], $this->response);

        $this->assertSame($this->request, $args[0]);
        $this->assertSame($this->response, $args[1]);
    }

    public function testUsesDefaultValueWhenNoArgumentProvided(): void
    {
        $fn = new ReflectionFunction(static fn ($page = 1) => null);

        $this->assertSame([1], $this->resolver()->forReflection($fn, [], $this->response));
    }

    public function testUsesNullForNullableParameterWithoutArgument(): void
    {
        $fn = new ReflectionFunction(static fn (?string $q) => null);

        $this->assertSame([null], $this->resolver()->forReflection($fn, [], $this->response));
    }

    public function testThrowsForRequiredBuiltinParameterThatCannotBeFilled(): void
    {
        // B5 regression: previously the parameter was silently skipped, which
        // shifted positional arguments and caused an ArgumentCountError later.
        $fn = new ReflectionFunction(static fn (string $required) => null);

        $this->expectException(RouterException::class);
        $this->resolver()->forReflection($fn, [], $this->response);
    }

    public function testMakeReturnsSharedRequestForRequestTypedClass(): void
    {
        $made = $this->resolver()->make(new ReflectionClass($this->request), $this->response);

        $this->assertSame($this->request, $made);
    }

    public function testMakeInstantiatesAndResolvesConstructorDependencies(): void
    {
        $service = $this->resolver()->make(new ReflectionClass(DependentService::class), $this->response);

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

        $made = $this->resolver($container)->make(new ReflectionClass(PlainService::class), $this->response);

        $this->assertSame($sentinel, $made);
    }

    public function testMakeFallsBackToInstantiationWhenContainerReturnsNonObject(): void
    {
        $container = new class () implements ContainerInterface {
            public function get(string $id): mixed
            {
                return 'not-an-object';
            }

            public function has(string $id): bool
            {
                return false;
            }
        };

        $made = $this->resolver($container)->make(new ReflectionClass(PlainService::class), $this->response);

        $this->assertInstanceOf(PlainService::class, $made);
    }

    public function testMakePassesNullToNullableScalarConstructorDependency(): void
    {
        $made = $this->resolver()->make(new ReflectionClass(NullableScalarDependency::class), $this->response);

        $this->assertInstanceOf(NullableScalarDependency::class, $made);
        $this->assertNull($made->name);
    }

    public function testMakeThrowsForUnresolvableScalarConstructorDependency(): void
    {
        $this->expectException(RouterException::class);
        $this->resolver()->make(new ReflectionClass(RequiredScalarDependency::class), $this->response);
    }

    public function testWithNewInstanceTheSharedResponseIsNotInjected(): void
    {
        // argument_new_instance on: the shared request/response shortcut is off,
        // and a non-instantiable interface type cannot be built, so it is
        // skipped (whereas with the flag off the shared response is injected).
        $fn = new ReflectionFunction(static fn (ResponseInterface $res) => $res);

        $this->assertSame([], $this->resolver(null, true)->forReflection($fn, [], $this->response));
        $this->assertSame([$this->response], $this->resolver(null, false)->forReflection($fn, [], $this->response));
    }
}
