<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling\Filter\Operators\Package;

use Composer\Package\PackageInterface;
use WyriHaximus\Composer\GenerativePluginTooling\PackageFilter;

use function array_any;

final readonly class LogicalOr implements PackageFilter
{
    /** @var array<PackageFilter> */
    private array $filters;

    public function __construct(
        PackageFilter ...$filters,
    ) {
        $this->filters = $filters;
    }

    public function __invoke(PackageInterface $package): bool
    {
        return array_any($this->filters, static fn (PackageFilter $packageFilter): bool => $packageFilter($package));
    }
}
