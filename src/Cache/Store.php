<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling\Cache;

use Composer\IO\IOInterface;
use Throwable;
use WyriHaximus\Composer\GenerativePluginTooling\Cache;
use WyriHaximus\Composer\GenerativePluginTooling\FailedReflectionsStore;

use function assert;
use function dirname;
use function error_get_last;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_dir;
use function is_string;
use function json_decode;
use function json_encode;
use function mkdir;
use function rtrim;

use const DIRECTORY_SEPARATOR;

final class Store
{
    private static self|null $instance = null;

    private static Cache|null $disabledCache = null;

    private readonly Cache $cache;

    private function __construct(
        private readonly CacheFilePath $cacheFilePath,
        private readonly string $vendorDir,
    ) {
        /** @var array<mixed> $json */
        $json = [];
        if ((string) $this->cacheFilePath !== '' && file_exists((string) $this->cacheFilePath)) {
            $cacheContents = file_get_contents((string) $this->cacheFilePath);
            if (is_string($cacheContents)) {
                $decoded = json_decode($cacheContents, true);
                if (is_array($decoded)) {
                    $json = $decoded;
                }
            }
        }

        $installedJsonPath = $this->installedJsonPath();
        $cache             = Cache::fromJSON($json, $this->cacheFilePath->root);
        if (! $cache->installedJsonHashMatches($installedJsonPath)) {
            $cache = Cache::fromJSON([], $this->cacheFilePath->root);
        }

        $this->cache = $cache;

        foreach ($this->cache->failedReflectionClasses() as $class) {
            FailedReflectionsStore::add($class);
        }
    }

    public static function setUp(CacheFilePath $cacheFilePath, string $vendorDir): void
    {
        if (self::$instance instanceof self) {
            return;
        }

        self::$instance = new self($cacheFilePath, $vendorDir);
    }

    public static function isEnabled(): bool
    {
        return self::$instance instanceof self;
    }

    public static function store(IOInterface $io): bool
    {
        if (! self::isEnabled()) {
            return false;
        }

        $instance = self::$instance;
        if (! $instance instanceof self) {
            return false;
        }

        if ((string) $instance->cacheFilePath === '') {
            return false;
        }

        try {
            $instance->cache->setInstalledJsonHash($instance->installedJsonPath());

            $cacheDirectory = dirname((string) $instance->cacheFilePath);
            if (! is_dir($cacheDirectory) && ! mkdir($cacheDirectory, recursive: true)) {
                self::logError($io, 'Failed to create cache directory: ' . $cacheDirectory);

                return false;
            }

            $encoded = json_encode($instance->cache, JSON_PRETTY_PRINT);
            if ($encoded === false) {
                self::logError($io, 'Failed to encode cache as JSON');

                return false;
            }

            if (file_put_contents((string) $instance->cacheFilePath, $encoded) === false) {
                self::logError($io, 'Failed to write cache file: ' . (string) $instance->cacheFilePath);

                return false;
            }

            return true;
        } catch (Throwable $throwable) {
            self::logError($io, 'Failed to write cache: ' . $throwable->getMessage());

            return false;
        }
    }

    public static function cache(): Cache
    {
        if (! self::isEnabled()) {
            self::$disabledCache ??= Cache::disabled();

            return self::$disabledCache;
        }

        $instance = self::$instance;
        assert($instance instanceof self);

        return $instance->cache;
    }

    /** @api */
    public static function reset(): void
    {
        self::$instance      = null;
        self::$disabledCache = null;
    }

    private function installedJsonPath(): string
    {
        return rtrim($this->vendorDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'installed.json';
    }

    private static function logError(IOInterface $io, string $message): void
    {
        $error  = error_get_last();
        $suffix = is_array($error) ? ' with error: ' . $error['message'] : '';

        $io->writeError('wyrihaximus/generative-composer-plugin-tooling: ' . $message . $suffix);
    }
}
