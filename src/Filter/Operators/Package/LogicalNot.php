<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling\Filter\Operators\Package;

use Composer\Package\PackageInterface;
use WyriHaximus\Composer\GenerativePluginTooling\PackageFilter;

final readonly class LogicalNot implements PackageFilter
{
    public function __construct(
        private PackageFilter $filter,
    ) {
    }

    public function __invoke(PackageInterface $package): bool
    {
        return ! ($this->filter)($package);
    }
}
