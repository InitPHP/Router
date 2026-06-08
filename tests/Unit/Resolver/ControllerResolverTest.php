<?php

declare(strict_types=1);

namespace Tests\Router\Unit\Resolver;

use InitPHP\Router\Exception\RouterException;
use InitPHP\Router\Resolver\ControllerResolver;
use PHPUnit\Framework\TestCase;
use Tests\Router\Unit\ExampleController;

final class ControllerResolverTest extends TestCase
{
    /**
     * @dataProvider stringHandlerProvider
     */
    public function testParsesStringHandlerSeparators(string $handler): void
    {
        $parsed = (new ControllerResolver())->parse($handler);

        $this->assertSame('Home', $parsed['controller']);
        $this->assertSame('index', $parsed['method']);
    }

    /** @return array<string, array{0: string}> */
    public function stringHandlerProvider(): array
    {
        return [
            'at'           => ['Home@index'],
            'double-colon' => ['Home::index'],
            'arrow'        => ['Home->index'],
        ];
    }

    public function testParsesListArrayHandler(): void
    {
        $parsed = (new ControllerResolver())->parse(['Home', 'index']);

        $this->assertSame(['controller' => 'Home', 'method' => 'index'], $parsed);
    }

    public function testParsesMapArrayHandler(): void
    {
        $parsed = (new ControllerResolver())->parse(['Home' => 'index']);

        $this->assertSame(['controller' => 'Home', 'method' => 'index'], $parsed);
    }

    public function testParseThrowsWhenHandlerHasNoSeparator(): void
    {
        $this->expectException(RouterException::class);
        (new ControllerResolver())->parse('JustAClassName');
    }

    public function testFindReturnsAnExistingFullyQualifiedClassAsIs(): void
    {
        $resolver = new ControllerResolver();

        $this->assertSame(ExampleController::class, $resolver->find(ExampleController::class));
    }

    public function testFindResolvesABareClassAgainstTheNamespace(): void
    {
        $resolver = new ControllerResolver('\\Tests\\Router\\Unit');

        $this->assertSame('\\Tests\\Router\\Unit\\ExampleController', $resolver->find('ExampleController'));
    }

    public function testFindReturnsObjectsUntouched(): void
    {
        $controller = new ExampleController();

        $this->assertSame($controller, (new ControllerResolver())->find($controller));
    }

    public function testFindThrowsWhenControllerCannotBeLocated(): void
    {
        $this->expectException(RouterException::class);
        (new ControllerResolver())->find('NoSuchControllerHere');
    }
}
