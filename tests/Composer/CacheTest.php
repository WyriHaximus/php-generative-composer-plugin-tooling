<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling\Composer;

use Composer\Composer;
use Composer\Config;
use Composer\Package\RootPackage;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use WyriHaximus\Composer\GenerativePluginTooling\Cache\Store;
use WyriHaximus\Composer\GenerativePluginTooling\Composer\Cache;
use WyriHaximus\Composer\GenerativePluginTooling\Composer\CacheLocator;
use WyriHaximus\Tests\Composer\GenerativePluginTooling\TestIo;
use WyriHaximus\TestUtilities\TestCase;

use function file_exists;
use function mkdir;
use function unlink;

use const DIRECTORY_SEPARATOR;
use const PHP_INT_MAX;
use const PHP_INT_MIN;

final class CacheTest extends TestCase
{
    #[Before]
    public function resetSingletons(): void
    {
        Store::reset();
        $instance = new ReflectionProperty(CacheLocator::class, 'instance');
        $instance->setValue(null, null);
    }

    #[Test]
    public function getSubscribedEvents(): void
    {
        self::assertSame(
            [
                ScriptEvents::PRE_AUTOLOAD_DUMP => [
                    ['loadCache', PHP_INT_MAX],
                    ['saveCache', PHP_INT_MIN],
                ],
            ],
            Cache::getSubscribedEvents(),
        );
    }

    #[Test]
    public function pluginLifecycleMethodsDoNothing(): void
    {
        self::expectNotToPerformAssertions();

        $plugin   = new Cache();
        $composer = new Composer();
        $io       = new TestIo();

        $plugin->activate($composer, $io);
        $plugin->deactivate($composer, $io);
        $plugin->uninstall($composer, $io);
    }

    #[Test]
    public function loadCacheReturnsWhenCacheIsNotConfigured(): void
    {
        $io = new TestIo();

        Cache::loadCache($this->createEvent($io, []));

        self::assertSame('', $io->output());
        self::assertFalse(Store::isEnabled());
    }

    #[Test]
    public function loadCacheSetsUpStoreWhenConfigured(): void
    {
        $io        = new TestIo();
        $cacheFile = 'var/composer-cache-plugin-load.json';
        $event     = $this->createEvent($io, [
            'wyrihaximus' => [
                'generative-composer-plugin-tooling' => ['cache' => $cacheFile],
            ],
        ]);

        Cache::loadCache($event);

        self::assertTrue(Store::isEnabled());
        self::assertStringContainsString('Loading Cache', $io->output());
        self::assertStringContainsString('Loaded Cache', $io->output());
    }

    #[Test]
    public function saveCacheReturnsWhenCacheIsNotConfigured(): void
    {
        $io = new TestIo();

        Cache::saveCache($this->createEvent($io, []));

        self::assertSame('', $io->output());
    }

    #[Test]
    public function saveCacheReturnsWhenStoreFails(): void
    {
        $io    = new TestIo();
        $event = $this->createEvent($io, [
            'wyrihaximus' => [
                'generative-composer-plugin-tooling' => ['cache' => 'var/composer-cache-plugin-save-fail.json'],
            ],
        ]);

        Cache::saveCache($event);

        self::assertStringContainsString('Storing Cache', $io->output());
        self::assertStringNotContainsString('Stored Cache', $io->output());
    }

    #[Test]
    public function saveCacheStoresWhenStoreSucceeds(): void
    {
        $io                = new TestIo();
        $cacheFile         = 'var/composer-cache-plugin-save.json';
        $event             = $this->createEvent($io, [
            'wyrihaximus' => [
                'generative-composer-plugin-tooling' => ['cache' => $cacheFile],
            ],
        ]);
        $absoluteCacheFile = $this->getTmpDir() . $cacheFile;
        if (file_exists($absoluteCacheFile)) {
            unlink($absoluteCacheFile);
        }

        Cache::loadCache($event);
        Cache::saveCache($event);

        self::assertStringContainsString('Stored Cache', $io->output());
        self::assertFileExists($absoluteCacheFile);
    }

    /** @param array<mixed> $extra */
    private function createEvent(TestIo $io, array $extra): Event
    {
        $vendorDir = $this->getTmpDir() . 'vendor' . DIRECTORY_SEPARATOR;
        mkdir($vendorDir . 'composer', 0o755, true);

        $config = new Config();
        $config->merge(['config' => ['vendor-dir' => $vendorDir]]);

        $package = new RootPackage('wyrihaximus/generative-composer-plugin-tooling', 'dev-main', 'dev-main');
        $package->setExtra($extra);

        $composer = new Composer();
        $composer->setConfig($config);
        $composer->setPackage($package);

        return new Event(ScriptEvents::PRE_AUTOLOAD_DUMP, $composer, $io);
    }
}
