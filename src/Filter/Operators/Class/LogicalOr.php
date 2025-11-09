<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling\Filter\Operators\Class;

use Roave\BetterReflection\Reflection\ReflectionClass;
use WyriHaximus\Composer\GenerativePluginTooling\ClassFilter;

use function array_any;

final readonly class LogicalOr implements ClassFilter
{
    /** @var array<ClassFilter> */
    private array $filters;

    public function __construct(
        ClassFilter ...$filters,
    ) {
        $this->filters = $filters;
    }

    public function __invoke(ReflectionClass $class): bool
    {
        return array_any($this->filters, static fn (ClassFilter $classFilter): bool => $classFilter($class));
    }
}
