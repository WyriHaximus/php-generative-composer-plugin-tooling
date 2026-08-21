<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling\Cache;

use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use WyriHaximus\Composer\GenerativePluginTooling\Cache;
use WyriHaximus\Composer\GenerativePluginTooling\Cache\CacheFilePath;
use WyriHaximus\Composer\GenerativePluginTooling\Cache\Store;
use WyriHaximus\Composer\GenerativePluginTooling\FailedReflectionsStore;
use WyriHaximus\Tests\Composer\GenerativePluginTooling\Plugin;
use WyriHaximus\Tests\Composer\GenerativePluginTooling\Support\SuppressExpectedErrors;
use WyriHaximus\Tests\Composer\GenerativePluginTooling\TestIo;
use WyriHaximus\TestUtilities\TestCase;

use function error_clear_last;
use function file_exists;
use function file_put_contents;
use function fopen;
use function is_dir;
use function json_encode;
use function md5_file;
use function mkdir;
use function unlink;

use const DIRECTORY_SEPARATOR;

final class StoreTest extends TestCase
{
    #[Before]
    public function resetStores(): void
    {
        Store::reset();
        FailedReflectionsStore::reset();
    }

    #[Test]
    public function setUpIsIdempotent(): void
    {
        $cacheFilePath = $this->cacheFilePath('idempotent.json');

        Store::setUp($cacheFilePath, $this->vendorDir());
        self::assertTrue(Store::isEnabled());

        Store::setUp(new CacheFilePath('', ''), $this->vendorDir());
        self::assertTrue(Store::isEnabled());
        self::assertTrue(Store::cache()->isEnabled());
    }

    #[Test]
    public function constructLoadsMatchingCacheAndFailedReflections(): void
    {
        $vendorDir         = $this->vendorDir();
        $installedJsonPath = $vendorDir . 'composer' . DIRECTORY_SEPARATOR . 'installed.json';
        mkdir($vendorDir . 'composer', 0o755, true);
        file_put_contents($installedJsonPath, '{"packages":[]}');

        $hash = md5_file($installedJsonPath);
        self::assertNotFalse($hash);

        $cacheFilePath = $this->cacheFilePath('matching.json');
        $this->writeCacheFile($cacheFilePath, [
            'installedJsonHash' => $hash,
            'failedReflections' => [Plugin::class],
        ]);

        Store::setUp($cacheFilePath, $vendorDir);

        self::assertTrue(Store::cache()->hasFailedReflection(Plugin::class));
        self::assertTrue(FailedReflectionsStore::has(Plugin::class));
    }

    #[Test]
    public function constructResetsCacheWhenInstalledJsonHashDoesNotMatch(): void
    {
        $vendorDir = $this->vendorDir();
        mkdir($vendorDir . 'composer', 0o755, true);
        file_put_contents($vendorDir . 'composer' . DIRECTORY_SEPARATOR . 'installed.json', '{"packages":[]}');

        $cacheFilePath = $this->cacheFilePath('mismatch.json');
        $this->writeCacheFile($cacheFilePath, [
            'installedJsonHash' => 'not-the-real-hash',
            'failedReflections' => [Plugin::class],
            'fileHashes' => ['a.php' => 'hash'],
        ]);

        Store::setUp($cacheFilePath, $vendorDir);

        self::assertFalse(Store::cache()->hasFailedReflection(Plugin::class));
        self::assertSame([
            'classFilePaths' => [],
            'classFilterOutcome' => [],
            'collectedItems' => [],
            'failedReflections' => [],
            'fileHashes' => [],
            'installedJsonHash' => '',
        ], Store::cache()->jsonSerialize());
    }

    #[Test]
    public function constructIgnoresNonArrayCacheContents(): void
    {
        $cacheFilePath = $this->cacheFilePath('invalid.json');
        file_put_contents((string) $cacheFilePath, '"not-an-array"');

        Store::setUp($cacheFilePath, $this->vendorDir());

        self::assertSame([], Store::cache()->jsonSerialize()['fileHashes']);
    }

    #[Test]
    public function storeReturnsFalseWhenDisabled(): void
    {
        self::assertFalse(Store::store(new TestIo()));
    }

    #[Test]
    public function storeReturnsFalseWhenCacheFilePathIsEmpty(): void
    {
        Store::setUp(new CacheFilePath('', ''), $this->vendorDir());

        self::assertFalse(Store::store(new TestIo()));
    }

    #[Test]
    public function storeWritesCacheFile(): void
    {
        $io            = new TestIo();
        $cacheFilePath = $this->cacheFilePath('write.json');
        if (file_exists((string) $cacheFilePath)) {
            unlink((string) $cacheFilePath);
        }

        Store::setUp($cacheFilePath, $this->vendorDir());

        self::assertTrue(Store::store($io));
        self::assertFileExists((string) $cacheFilePath);
        self::assertSame('', $io->output());
    }

    #[Test]
    public function storeReturnsFalseWhenCacheDirectoryCannotBeCreated(): void
    {
        $root     = $this->getTmpDir();
        $blocking = $root . 'blocked-as-file';
        file_put_contents($blocking, 'not-a-directory');
        $cacheFilePath = new CacheFilePath($root, 'blocked-as-file/cache.json');
        $io            = new TestIo();

        Store::setUp($cacheFilePath, $this->vendorDir());

        SuppressExpectedErrors::during(static function () use ($io): void {
            self::assertFalse(Store::store($io));
        });
        self::assertStringContainsString('Failed to create cache directory', $io->output());
        self::assertStringContainsString('with error:', $io->output());
    }

    #[Test]
    public function storeReturnsFalseWhenJsonEncodeFails(): void
    {
        $cacheFilePath = $this->cacheFilePath('encode-fail.json');
        Store::setUp($cacheFilePath, $this->vendorDir());

        $cacheProperty = new ReflectionProperty(Store::class, 'cache');
        $instance      = new ReflectionProperty(Store::class, 'instance');
        /** @var Store $store */
        $store = $instance->getValue();
        $cache = $cacheProperty->getValue($store);
        self::assertInstanceOf(Cache::class, $cache);

        $collectedItems = new ReflectionProperty(Cache::class, 'collectedItems');
        $handle         = fopen('php://memory', 'rb');
        self::assertNotFalse($handle);
        $collectedItems->setValue($cache, ['plugin' => ['class' => ['collector' => [$handle]]]]);

        error_clear_last();
        $io = new TestIo();

        self::assertFalse(Store::store($io));
        self::assertStringContainsString('Failed to encode cache as JSON', $io->output());
        self::assertStringNotContainsString('with error:', $io->output());
    }

    #[Test]
    public function storeReturnsFalseWhenCacheFileCannotBeWritten(): void
    {
        $cacheFilePath = $this->cacheFilePath('dir-as-file');
        mkdir((string) $cacheFilePath, 0o755, true);

        SuppressExpectedErrors::during(function () use ($cacheFilePath): void {
            Store::setUp($cacheFilePath, $this->vendorDir());

            $io = new TestIo();

            self::assertFalse(Store::store($io));
            self::assertStringContainsString('Failed to write cache file', $io->output());
        });
    }

    #[Test]
    public function storeReturnsFalseWhenWritingThrows(): void
    {
        $cacheFilePath = new CacheFilePath($this->getTmpDir(), "cache\0null.json");
        Store::setUp($cacheFilePath, $this->vendorDir());

        $io = new TestIo();

        self::assertFalse(Store::store($io));
        self::assertStringContainsString('Failed to write cache:', $io->output());
    }

    private function vendorDir(): string
    {
        return $this->getTmpDir() . 'vendor' . DIRECTORY_SEPARATOR;
    }

    private function cacheFilePath(string $name): CacheFilePath
    {
        $directory = $this->getTmpDir() . 'var' . DIRECTORY_SEPARATOR;
        if (! is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }

        return new CacheFilePath($this->getTmpDir(), 'var' . DIRECTORY_SEPARATOR . $name);
    }

    /** @param array<string, mixed> $payload */
    private function writeCacheFile(CacheFilePath $cacheFilePath, array $payload): void
    {
        file_put_contents((string) $cacheFilePath, (string) json_encode([
            'installedJsonHash' => '',
            'failedReflections' => [],
            'fileHashes' => [],
            'classFilterOutcome' => [],
            'classFilePaths' => [],
            'collectedItems' => [],
            ...$payload,
        ]));
    }
}
