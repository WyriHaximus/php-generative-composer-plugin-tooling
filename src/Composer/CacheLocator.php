<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling\Composer;

use Composer\Composer;
use Composer\Package\RootPackageInterface;
use WyriHaximus\Composer\GenerativePluginTooling\Cache\CacheFilePath;

use function array_key_exists;
use function dirname;

use const DIRECTORY_SEPARATOR;

final class CacheLocator
{
    private static self|null $instance = null;

    private CacheFilePath|null $cacheLocation = null;

    public function __construct(string $vendorDir, RootPackageInterface $package)
    {
        $extra = $package->getExtra();
        if (
            ! array_key_exists('wyrihaximus', $extra) ||
            ! array_key_exists('generative-composer-plugin-tooling', $extra['wyrihaximus']) ||
            ! array_key_exists('cache', $extra['wyrihaximus']['generative-composer-plugin-tooling'])
        ) {
            return;
        }

        $this->cacheLocation = new CacheFilePath(
            dirname($vendorDir) . DIRECTORY_SEPARATOR,
            $extra['wyrihaximus']['generative-composer-plugin-tooling']['cache'],
        );
    }

    private static function instance(string $vendorDir, RootPackageInterface $package): self
    {
        if (! self::$instance instanceof self) {
            self::$instance = new self($vendorDir, $package);
        }

        return self::$instance;
    }

    public static function locate(Composer $composer): CacheFilePath|null
    {
        return self::instance($composer->getConfig()->get('vendor-dir'), $composer->getPackage())->cacheLocation;
    }
}
