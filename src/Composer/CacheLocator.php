<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling\Composer;

use Composer\Composer;
use Composer\Package\RootPackageInterface;
use WyriHaximus\Composer\GenerativePluginTooling\Cache\CacheFilePath;

use function array_key_exists;
use function dirname;
use function is_array;
use function is_string;

use const DIRECTORY_SEPARATOR;

final class CacheLocator
{
    private static self|null $instance = null;

    private readonly CacheFilePath|null $cacheLocation;

    public function __construct(string $vendorDir, RootPackageInterface $package)
    {
        $this->cacheLocation = $this->cacheLocationFromExtra($vendorDir, $package->getExtra());
    }

    /** @param array<mixed> $extra */
    private function cacheLocationFromExtra(string $vendorDir, array $extra): CacheFilePath|null
    {
        if (! array_key_exists('wyrihaximus', $extra) || ! is_array($extra['wyrihaximus'])) {
            return null;
        }

        $wyrihaximus = $extra['wyrihaximus'];
        if (! array_key_exists('generative-composer-plugin-tooling', $wyrihaximus) || ! is_array($wyrihaximus['generative-composer-plugin-tooling'])) {
            return null;
        }

        $tooling = $wyrihaximus['generative-composer-plugin-tooling'];
        if (! array_key_exists('cache', $tooling) || ! is_string($tooling['cache'])) {
            return null;
        }

        return new CacheFilePath(
            dirname($vendorDir) . DIRECTORY_SEPARATOR,
            $tooling['cache'],
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
