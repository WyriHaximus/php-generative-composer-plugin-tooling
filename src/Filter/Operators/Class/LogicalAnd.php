<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling\Filter\Operators\Class;

use Composer\Package\PackageInterface;
use Roave\BetterReflection\Reflection\ReflectionClass;
use WyriHaximus\Composer\GenerativePluginTooling\ClassFilter;
use WyriHaximus\Composer\GenerativePluginTooling\PackageFilter;

use function array_all;
use function array_filter;

final readonly class LogicalAnd implements ClassFilter
{
    /** @var array<ClassFilter> */
    private array $filters;

    public function __construct(
        ClassFilter ...$filters,
    ) {
        $this->filters  = $filters;
    }

    public function __invoke(ReflectionClass $class): bool
    {
        return array_all($this->filters, static fn (ClassFilter $classFilter): bool => $classFilter($class));
    }
}
