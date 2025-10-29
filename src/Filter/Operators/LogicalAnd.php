<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling\Filter\Operators;

use Composer\Package\PackageInterface;
use Roave\BetterReflection\Reflection\ReflectionClass;
use WyriHaximus\Composer\GenerativePluginTooling\ClassFilter;
use WyriHaximus\Composer\GenerativePluginTooling\PackageFilter;

use function array_all;
use function array_filter;

final readonly class LogicalAnd
{
    /**
     * @return iterable<ClassFilter|PackageFilter>
     */
    public static function create(
        ClassFilter|PackageFilter ...$filters,
    ): iterable {
        $classFilters = array_filter($filters, static fn (ClassFilter|PackageFilter $filter): bool => $filter instanceof ClassFilter);
        if (count($classFilters) > 0) {
            yield new \WyriHaximus\Composer\GenerativePluginTooling\Filter\Operators\Class\LogicalAnd(...$classFilters);
        }
        $packageFilters = array_filter($filters, static fn (ClassFilter|PackageFilter $filter): bool => $filter instanceof PackageFilter);
        if (count($packageFilters) > 0) {
            yield new \WyriHaximus\Composer\GenerativePluginTooling\Filter\Operators\Package\LogicalAnd(...$packageFilters);
        }
    }
}
