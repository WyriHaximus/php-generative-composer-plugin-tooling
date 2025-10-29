<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling\Filter\Operators;

use WyriHaximus\Composer\GenerativePluginTooling\ClassFilter;
use WyriHaximus\Composer\GenerativePluginTooling\PackageFilter;

use function array_filter;
use function count;

final readonly class LogicalOr
{
    /** @return iterable<ClassFilter|PackageFilter> */
    public static function create(
        ClassFilter|PackageFilter ...$filters,
    ): iterable {
        $classFilters = array_filter($filters, static fn (ClassFilter|PackageFilter $filter): bool => $filter instanceof ClassFilter);
        if (count($classFilters) > 0) {
            yield new \WyriHaximus\Composer\GenerativePluginTooling\Filter\Operators\Class\LogicalOr(...$classFilters);
        }

        $packageFilters = array_filter($filters, static fn (ClassFilter|PackageFilter $filter): bool => $filter instanceof PackageFilter);
        if (count($packageFilters) <= 0) {
            return;
        }

        yield new \WyriHaximus\Composer\GenerativePluginTooling\Filter\Operators\Package\LogicalOr(...$packageFilters);
    }
}
