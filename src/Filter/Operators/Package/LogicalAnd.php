<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling\Filter\Operators\Package;

use Composer\Package\PackageInterface;
use Roave\BetterReflection\Reflection\ReflectionClass;
use WyriHaximus\Composer\GenerativePluginTooling\ClassFilter;
use WyriHaximus\Composer\GenerativePluginTooling\PackageFilter;

use function array_all;
use function array_filter;

final readonly class LogicalAnd implements PackageFilter
{
    /** @var array<PackageFilter> */
    private array $filters;

    public function __construct(
        PackageFilter ...$filters,
    ) {
        $this->filters  = $filters;
    }

    public function __invoke(PackageInterface $package): bool
    {
        return array_all($this->filters, static fn (PackageFilter $packageFilter): bool => $packageFilter($package));
    }
}
