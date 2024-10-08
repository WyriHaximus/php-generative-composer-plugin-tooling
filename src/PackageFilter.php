<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling;

use Composer\Package\PackageInterface;

interface PackageFilter
{
    public function __invoke(PackageInterface $package): bool;
}
