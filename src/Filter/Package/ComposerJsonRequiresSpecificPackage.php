<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling\Filter\Package;

use Composer\Package\PackageInterface;
use WyriHaximus\Composer\GenerativePluginTooling\PackageFilter;

/** @api */
final readonly class ComposerJsonRequiresSpecificPackage implements PackageFilter
{
    private const bool PACKAGE_FOUND_RETURN     = true;
    private const bool PACKAGE_NOT_FOUND_RETURN = false;

    public function __construct(private string $package, private PackageType $type)
    {
    }

    public function __invoke(PackageInterface $package): bool
    {
        $links = $this->type === PackageType::PRODUCTION ? $package->getRequires() : $package->getDevRequires();
        foreach ($links as $link) {
            if ($link->getTarget() === $this->package) {
                return self::PACKAGE_FOUND_RETURN;
            }
        }

        return self::PACKAGE_NOT_FOUND_RETURN;
    }
}
