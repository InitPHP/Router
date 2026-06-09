<?php

declare(strict_types=1);

namespace Tests\Router\Unit\Resolver;

final class PlainService
{
    public bool $built = true;
}

final class DependentService
{
    public function __construct(public PlainService $dependency)
    {
    }
}

final class NullableScalarDependency
{
    public function __construct(public ?string $name)
    {
    }
}

final class RequiredScalarDependency
{
    public function __construct(public string $name)
    {
    }
}
