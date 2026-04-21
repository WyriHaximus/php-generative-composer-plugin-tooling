<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling;

use Composer\Composer;
use Composer\Config;
use Composer\Factory;
use Composer\Package\RootPackage;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\RepositoryManager;
use Mockery;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\Broadcast\Dummy\AsyncListener;
use WyriHaximus\Broadcast\Dummy\Event;
use WyriHaximus\Broadcast\Dummy\Listener;
use WyriHaximus\Composer\GenerativePluginTooling\Cache\CacheFilePath;
use WyriHaximus\Composer\GenerativePluginTooling\Cache\Store;
use WyriHaximus\Composer\GenerativePluginTooling\ClassReflectorStore;
use WyriHaximus\Composer\GenerativePluginTooling\FailedReflectionsStore;
use WyriHaximus\Composer\GenerativePluginTooling\GenerativePluginExecutioner;
use WyriHaximus\Composer\GenerativePluginTooling\Item as ItemContract;
use WyriHaximus\Composer\GenerativePluginTooling\ReflectionsStore;
use WyriHaximus\TestUtilities\TestCase;

use function dirname;
use function file_exists;
use function file_get_contents;
use function json_decode;
use function json_encode;
use function unlink;
use function usort;

use const DIRECTORY_SEPARATOR;

final class GenerativePluginExecutionerTest extends TestCase
{
    /** @return iterable<string, array<string>> */
    public static function apps(): iterable
    {
        yield 'broadcast' => ['broadcast'];
        yield 'broadcast-classmap' => ['broadcast-classmap'];
    }

    #[Test]
    #[DataProvider('apps')]
    public function broadcast(string $app): void
    {
        $composer = $this->createComposer($app);
        $io       = new TestIo();

        $plugin = new Plugin();
        GenerativePluginExecutioner::execute($composer, $io, $plugin);

        $output = $io->output();

        $items = [
            new Item(
                Event::class,
                Listener::class,
                'handle',
                false,
                false,
            ),
            new Item(
                Event::class,
                Listener::class,
                'handleBoth',
                false,
                false,
            ),
            new Item(
                'stdClass',
                Listener::class,
                'handleBoth',
                false,
                false,
            ),
            new Item(
                Event::class,
                Listener::class,
                'doNotHandle',
                false,
                false,
            ),
            new Item(
                Event::class,
                AsyncListener::class,
                'handle',
                false,
                false,
            ),
        ];

        self::assertEquals([...$this->sortItems(...$items)], [...$this->sortItems(...$plugin->items())]);

        self::assertStringContainsString('<info>wyrihaximus/makefiles:</info> Locating listeners', $output);
        self::assertStringContainsString('<info>wyrihaximus/makefiles:</info> Found 5 listener(s)', $output);
        self::assertStringContainsString('<error>wyrihaximus/makefiles:</error> An error occurred: Cannot reflect "<fg=cyan>WyriHaximus\Broadcast\Dummy\BrokenAsyncListener</>": <fg=yellow>Roave\BetterReflection\Reflection\ReflectionClass "WyriHaximus\Broadcast\Contracts\AsyncListener" could not be found in the located source</>', $output);
        self::assertStringContainsString('<info>wyrihaximus/makefiles:</info> Generated static abstract listeners provider in', $output);
    }

    #[Test]
    #[DataProvider('apps')]
    public function broadcastUsesCacheOnSecondRun(string $app): void
    {
        $composer = $this->createComposer($app, withCache: true);
        $io       = new TestIo();

        $plugin = new Plugin();
        GenerativePluginExecutioner::execute($composer, $io, $plugin);
        Store::store();

        ClassReflectorStore::reset();
        FailedReflectionsStore::reset();
        ReflectionsStore::reset();

        GenerativePluginExecutioner::execute($composer, $io, $plugin);

        $items = [
            new Item(
                Event::class,
                Listener::class,
                'handle',
                false,
                false,
            ),
            new Item(
                Event::class,
                Listener::class,
                'handleBoth',
                false,
                false,
            ),
            new Item(
                'stdClass',
                Listener::class,
                'handleBoth',
                false,
                false,
            ),
            new Item(
                Event::class,
                Listener::class,
                'doNotHandle',
                false,
                false,
            ),
            new Item(
                Event::class,
                AsyncListener::class,
                'handle',
                false,
                false,
            ),
        ];

        self::assertEquals([...$this->sortItems(...$items)], [...$this->sortItems(...$plugin->items())]);

        $cacheContents = file_get_contents($this->cacheFilePath($app));
        self::assertNotFalse($cacheContents);
        $cacheJson = json_decode($cacheContents, true);
        self::assertIsArray($cacheJson);
        self::assertNotSame('', $cacheJson['installedJsonHash'] ?? '');
        self::assertNotSame([], $cacheJson['collectedItems'] ?? []);
    }

    private function createComposer(string $app, bool $withCache = false): Composer
    {
        $vendorDir      = __DIR__ . DIRECTORY_SEPARATOR . 'apps' . DIRECTORY_SEPARATOR . $app . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR;
        $composerConfig = new Config();
        $composerConfig->merge([
            'config' => ['vendor-dir' => $vendorDir],
        ]);
        $extra = [
            'wyrihaximus' => [
                'broadcast' => ['has-listeners' => true],
            ],
        ];
        if ($withCache) {
            $extra['wyrihaximus']['generative-composer-plugin-tooling'] = [
                'cache' => 'var/' . $app . '-generative-plugin-cache.json',
            ];
            Store::setUp(
                new CacheFilePath(dirname($vendorDir) . DIRECTORY_SEPARATOR, 'var/' . $app . '-generative-plugin-cache.json'),
                $vendorDir,
            );
        }

        $rootPackage = new RootPackage('wyrihaximus/makefiles', 'dev-master', 'dev-master');
        $rootPackage->setExtra($extra);
        $rootPackage->setAutoload([
            'classmap' => ['dummy/event','dummy/listener/Listener.php'],
            'psr-4' => ['WyriHaximus\\Broadcast\\' => 'src'],
        ]);
        $io = new TestIo();

        $repository = Mockery::mock(InstalledRepositoryInterface::class);
        $repository->allows()->getCanonicalPackages();
        $repositoryManager = new RepositoryManager($io, $composerConfig, Factory::createHttpDownloader($io, $composerConfig));
        $repositoryManager->setLocalRepository($repository);
        $composer = new Composer();
        $composer->setConfig($composerConfig);
        $composer->setRepositoryManager($repositoryManager);
        $composer->setPackage($rootPackage);

        if ($withCache) {
            $cacheFile = $this->cacheFilePath($app);
            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }
        }

        return $composer;
    }

    private function cacheFilePath(string $app): string
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'apps' . DIRECTORY_SEPARATOR . $app . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . $app . '-generative-plugin-cache.json';
    }

    /** @return iterable<ItemContract> */
    private function sortItems(ItemContract ...$items): iterable
    {
        usort($items, static fn (ItemContract $a, ItemContract $b): int => (string) json_encode($a) <=> (string) json_encode($b));

        yield from $items;
    }

    #[Before]
    public function resetRFS(): void
    {
        ClassReflectorStore::reset();
        FailedReflectionsStore::reset();
        ReflectionsStore::reset();
        Store::reset();
    }
}
