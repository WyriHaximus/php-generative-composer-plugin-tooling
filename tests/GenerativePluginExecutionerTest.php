<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling;

use Composer\Composer;
use Composer\Config;
use Composer\Factory;
use Composer\Package\CompletePackage;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackage;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\RepositoryManager;
use Mockery;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use ReflectionProperty;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\SourceLocator\Type\Composer\Psr\Exception\InvalidPrefixMapping;
use RuntimeException;
use WyriHaximus\Broadcast\Dummy\AsyncListener;
use WyriHaximus\Broadcast\Dummy\Event;
use WyriHaximus\Broadcast\Dummy\Listener;
use WyriHaximus\Composer\GenerativePluginTooling\Cache\CacheFilePath;
use WyriHaximus\Composer\GenerativePluginTooling\Cache\Store;
use WyriHaximus\Composer\GenerativePluginTooling\ClassFilter;
use WyriHaximus\Composer\GenerativePluginTooling\ClassReflectorStore;
use WyriHaximus\Composer\GenerativePluginTooling\FailedReflectionsStore;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Class\IsInstantiable;
use WyriHaximus\Composer\GenerativePluginTooling\GenerativePluginExecutioner;
use WyriHaximus\Composer\GenerativePluginTooling\Item as ItemContract;
use WyriHaximus\Composer\GenerativePluginTooling\PackageFilter;
use WyriHaximus\Composer\GenerativePluginTooling\ReflectionsStore;
use WyriHaximus\Tests\Composer\GenerativePluginTooling\Support\FilesystemFixtures;
use WyriHaximus\Tests\Composer\GenerativePluginTooling\Support\SuppressExpectedErrors;
use WyriHaximus\TestUtilities\TestCase;

use function array_keys;
use function class_exists;
use function count;
use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function is_iterable;
use function iterator_to_array;
use function json_decode;
use function json_encode;
use function md5;
use function md5_file;
use function mkdir;
use function serialize;
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
        Store::store($io);

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

    #[Test]
    public function executeSkipsPackagesRejectedByPackageFilter(): void
    {
        $composer = $this->createComposer('broadcast');
        $io       = new TestIo();
        $plugin   = new ConfigurablePlugin(
            [
                new class () implements PackageFilter {
                    public function __invoke(PackageInterface $package): bool
                    {
                        return false;
                    }
                },
            ],
            [new Collector()],
        );

        GenerativePluginExecutioner::execute($composer, $io, $plugin);

        self::assertSame([], $plugin->compiledItems());
        self::assertStringContainsString('Found 0 listener(s)', $io->output());
    }

    #[Test]
    public function executeUsesCollectorCacheAfterReflectionWhenFiltersWereUncached(): void
    {
        $composer = $this->createComposer('broadcast', withCache: true);
        $io       = new TestIo();
        $plugin   = new Plugin();

        GenerativePluginExecutioner::execute($composer, $io, $plugin);
        Store::store($io);

        $className = Listener::class;
        $fileName  = Store::cache()->getClassAbsoluteFilePath($className);
        self::assertNotNull($fileName);

        $filterOutcomes = new ReflectionProperty(Store::cache(), 'classFilterOutcome');
        $filterOutcomes->setValue(Store::cache(), []);

        ClassReflectorStore::reset();
        FailedReflectionsStore::reset();
        ReflectionsStore::reset();

        $plugin = new Plugin();
        GenerativePluginExecutioner::execute($composer, $io, $plugin);

        self::assertGreaterThan(0, count([...$plugin->items()]));
    }

    #[Test]
    public function locateRootPackageInstallPathForNonRootPluginPackage(): void
    {
        $composer = $this->createComposer('broadcast');
        $path     = $this->invoke(
            'locateRootPackageInstallPath',
            new NameMismatchPlugin(),
            $composer->getConfig(),
            $composer->getPackage(),
        );

        self::assertSame($composer->getConfig()->get('vendor-dir') . '/' . NameMismatchPlugin::name(), $path);
    }

    #[Test]
    public function locateRootPackageInstallPathThrowsWhenVendorDirMissing(): void
    {
        $config = new Config();
        $config->merge(['config' => ['vendor-dir' => $this->getTmpDir() . '/missing-vendor']]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('vendor-dir most be a string');

        $this->invoke(
            'locateRootPackageInstallPath',
            new Plugin(),
            $config,
            new RootPackage('wyrihaximus/makefiles', 'dev-master', 'dev-master'),
        );
    }

    #[Test]
    public function getVendorDirThrowsWhenVendorDirMissing(): void
    {
        $composerConfig = new Config();
        $composerConfig->merge(['config' => ['vendor-dir' => $this->getTmpDir() . '/nope']]);
        $composer = new Composer();
        $composer->setConfig($composerConfig);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('vendor-dir most be a string');

        $this->invoke('getVendorDir', $composer);
    }

    #[Test]
    public function autoloadablePackagesSkipsAutoloadWithoutClassmapOrPsr4(): void
    {
        $empty = new CompletePackage('vendor/empty', '1.0.0.0', '1.0.0');
        $files = new CompletePackage('vendor/files', '1.0.0.0', '1.0.0');
        $files->setAutoload(['files' => ['src/functions.php']]);
        $ok = new CompletePackage('vendor/ok', '1.0.0.0', '1.0.0');
        $ok->setAutoload(['psr-4' => ['Vendor\\Ok\\' => 'src/']]);

        $packages = $this->invoke('autoloadablePackages', $empty, $files, $ok);
        self::assertIsIterable($packages);

        self::assertSame(
            ['vendor/ok'],
            array_keys(iterator_to_array($packages, true)),
        );
    }

    #[Test]
    public function autoloadPathsCoversVendorPsr4ClassmapAndEdgeCases(): void
    {
        $vendorDir = $this->getTmpDir() . DIRECTORY_SEPARATOR . 'vendor';
        $pkgDir    = $vendorDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'pkg';
        mkdir($pkgDir . DIRECTORY_SEPARATOR . 'src', 0o755, true);
        mkdir($pkgDir . DIRECTORY_SEPARATOR . 'lib', 0o755, true);
        file_put_contents($pkgDir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'A.php', '<?php class A {}');
        file_put_contents($pkgDir . DIRECTORY_SEPARATOR . 'mapped.php', '<?php class Mapped {}');

        $vendorPackage = new CompletePackage('vendor/pkg', '1.0.0.0', '1.0.0');
        $vendorPackage->setAutoload([
            'psr-4' => [
                'Vendor\\Pkg\\' => 'src/',
                'Vendor\\Missing\\' => 'missing/',
                'Vendor\\Multi\\' => ['lib/', 'also-missing/'],
            ],
            'classmap' => ['mapped.php', 'missing-map.php'],
        ]);

        $rootPackage = new RootPackage('root/app', 'dev-master', 'dev-master');
        $rootPackage->setAutoload([
            'psr-4' => ['Root\\' => 'src'],
            'classmap' => [
                'relative',
                dirname($vendorDir) . DIRECTORY_SEPARATOR . 'absolute-classmap',
            ],
        ]);

        $psr4Only = new CompletePackage('vendor/psr4', '1.0.0.0', '1.0.0');
        $psr4Only->setAutoload(['psr-4' => ['Vendor\\Psr4\\' => 'src/']]);
        mkdir($vendorDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'psr4' . DIRECTORY_SEPARATOR . 'src', 0o755, true);

        $vendorPaths = $this->invoke('autoloadPaths', $vendorDir, $vendorPackage);
        $rootPaths   = $this->invoke('autoloadPaths', $vendorDir, $rootPackage);
        $psr4Paths   = $this->invoke('autoloadPaths', $vendorDir, $psr4Only);
        self::assertIsIterable($vendorPaths);
        self::assertIsIterable($rootPaths);
        self::assertIsIterable($psr4Paths);

        $paths = [...$vendorPaths, ...$rootPaths, ...$psr4Paths];

        self::assertContains($pkgDir . DIRECTORY_SEPARATOR . 'src', $paths);
        self::assertContains($pkgDir . DIRECTORY_SEPARATOR . 'mapped.php', $paths);
        self::assertContains(dirname($vendorDir) . DIRECTORY_SEPARATOR . 'src', $paths);
        self::assertContains(dirname($vendorDir) . DIRECTORY_SEPARATOR . 'absolute-classmap', $paths);
        self::assertContains($vendorDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'psr4' . DIRECTORY_SEPARATOR . 'src', $paths);
    }

    #[Test]
    public function listClassesInPathsYieldsClassesFromFile(): void
    {
        $file = $this->getTmpDir() . DIRECTORY_SEPARATOR . 'Single.php';
        file_put_contents($file, "<?php\n\nclass GenerativePluginExecutionerSingleFile {}\n");

        $classes = $this->invoke('listClassesInPaths', $file);
        self::assertIsIterable($classes);

        self::assertSame(
            ['GenerativePluginExecutionerSingleFile'],
            [...$classes],
        );
    }

    #[Test]
    public function loadVendorDirPackagesSkipsInvalidComposerJsonFiles(): void
    {
        $vendorDir = $this->getTmpDir() . DIRECTORY_SEPARATOR . 'vendor';
        $this->writeVendorComposerJson($vendorDir, 'bad/json', 'not-json');
        $this->writeVendorComposerJson($vendorDir, 'bad/scalar', '"string"');
        $this->writeVendorComposerJson($vendorDir, 'bad/noname', '{"description":"x"}');
        $this->writeVendorComposerJson($vendorDir, 'bad/namenotstring', '{"name":123}');
        $this->writeVendorComposerJson(
            $vendorDir,
            'bad/inf',
            '{"name":"wyrihaximus/simple-twig","extra":{"n":1e309}}',
        );
        $unreadableDir = $vendorDir . DIRECTORY_SEPARATOR . 'bad' . DIRECTORY_SEPARATOR . 'unreadable' . DIRECTORY_SEPARATOR . 'composer.json';
        FilesystemFixtures::createUnreadableFilesystemPath($unreadableDir);
        $this->writeVendorComposerJson(
            $vendorDir,
            'ok/pkg',
            '{"name":"wyrihaximus/makefiles","extra":{"wyrihaximus":{"broadcast":{"has-listeners":true}}}}',
        );

        $packages = $this->loadVendorDirPackages($vendorDir);

        self::assertCount(1, $packages);
        self::assertSame('wyrihaximus/makefiles', $packages[0]->getName());
    }

    #[Test]
    public function createAutoloaderLoadsMatchingPsr4Class(): void
    {
        $vendorDir = $this->getTmpDir() . DIRECTORY_SEPARATOR . 'vendor';
        $srcDir    = $vendorDir . DIRECTORY_SEPARATOR . 'wyrihaximus' . DIRECTORY_SEPARATOR . 'simple-twig' . DIRECTORY_SEPARATOR . 'src';
        mkdir($srcDir, 0o755, true);
        file_put_contents(
            $srcDir . DIRECTORY_SEPARATOR . 'AutoloadProbe.php',
            "<?php\n\nnamespace WyriHaximus\\Tests\\Composer\\GenerativePluginTooling\\AutoloadProbeNs;\n\nfinal class AutoloadProbe {}\n",
        );

        $package = new CompletePackage('wyrihaximus/simple-twig', '1.0.0.0', '1.0.0');
        $package->setAutoload([
            'psr-4' => [
                'WyriHaximus\\Tests\\Composer\\GenerativePluginTooling\\AutoloadProbeNs\\' => 'src/',
                'Other\\' => ['lib/', 'src/'],
            ],
            'classmap' => ['ignored.php'],
        ]);
        $noPsr4 = new CompletePackage('vendor/classmap-only', '1.0.0.0', '1.0.0');
        $noPsr4->setAutoload(['classmap' => ['src/']]);

        $autoloader = $this->invoke('createAutoloader', $vendorDir, $noPsr4, $package);
        self::assertIsCallable($autoloader);

        $autoloader('NoNamespace');
        $autoloader('Other\\Missing');
        $autoloader('WyriHaximus\\Tests\\Composer\\GenerativePluginTooling\\AutoloadProbeNs\\Missing');
        $autoloader('WyriHaximus\\Tests\\Composer\\GenerativePluginTooling\\AutoloadProbeNs\\AutoloadProbe');

        self::assertTrue(class_exists('WyriHaximus\\Tests\\Composer\\GenerativePluginTooling\\AutoloadProbeNs\\AutoloadProbe', false));
    }

    #[Test]
    public function createClassReflectorCreatesMissingAutoloadDirectory(): void
    {
        $root      = $this->getTmpDir() . DIRECTORY_SEPARATOR . 'app';
        $vendorDir = $root . DIRECTORY_SEPARATOR . 'vendor';
        mkdir($vendorDir . DIRECTORY_SEPARATOR . 'composer', 0o755, true);
        file_put_contents($root . DIRECTORY_SEPARATOR . 'composer.json', json_encode([
            'name' => 'wyrihaximus/makefiles',
            'autoload' => ['psr-4' => ['Missing\\Ns\\' => 'does-not-exist-yet']],
        ]));
        file_put_contents($vendorDir . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'installed.json', json_encode([
            'packages' => [],
            'dev' => true,
            'dev-package-names' => [],
        ]));

        self::assertFalse(is_dir($root . DIRECTORY_SEPARATOR . 'does-not-exist-yet'));

        $reflector = $this->invoke('createClassReflector', $vendorDir);
        self::assertNotNull($reflector);
        self::assertTrue(is_dir($root . DIRECTORY_SEPARATOR . 'does-not-exist-yet'));

        self::assertSame($reflector, $this->invoke('createClassReflector', $vendorDir));
    }

    #[Test]
    public function createClassReflectorRethrowsWhenAutoloadPathIsAFile(): void
    {
        $root      = $this->getTmpDir() . DIRECTORY_SEPARATOR . 'app-file-autoload';
        $vendorDir = $root . DIRECTORY_SEPARATOR . 'vendor';
        mkdir($vendorDir . DIRECTORY_SEPARATOR . 'composer', 0o755, true);
        file_put_contents($root . DIRECTORY_SEPARATOR . 'composer.json', json_encode([
            'name' => 'wyrihaximus/makefiles',
            'autoload' => ['psr-4' => ['Missing\\Ns\\' => 'not-a-directory']],
        ]));
        file_put_contents($vendorDir . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'installed.json', json_encode([
            'packages' => [],
            'dev' => true,
            'dev-package-names' => [],
        ]));
        file_put_contents($root . DIRECTORY_SEPARATOR . 'not-a-directory', 'file');

        $this->expectException(InvalidPrefixMapping::class);
        $this->invoke('createClassReflector', $vendorDir);
    }

    #[Test]
    public function missingAutoloadDirectoryFromExceptionExtractsPath(): void
    {
        $path = $this->invoke(
            'missingAutoloadDirectoryFromException',
            InvalidPrefixMapping::prefixMappingIsNotADirectory('Prefix\\', '/tmp/missing-autoload'),
        );

        self::assertSame('/tmp/missing-autoload', $path);
    }

    #[Test]
    public function missingAutoloadDirectoryFromExceptionRejectsUnrecognizedMessages(): void
    {
        $this->expectException(InvalidPrefixMapping::class);
        $this->invoke('missingAutoloadDirectoryFromException', InvalidPrefixMapping::emptyPrefixGiven());
    }

    #[Test]
    public function reflectClassUsesFailedAndReflectionStores(): void
    {
        $composer  = $this->createComposer('broadcast', withCache: true);
        $vendorDir = $composer->getConfig()->get('vendor-dir');
        $io        = new TestIo();
        $plugin    = new Plugin();

        FailedReflectionsStore::add(Listener::class);
        self::assertNull($this->invoke('reflectClass', $plugin, $io, $vendorDir, Listener::class));

        FailedReflectionsStore::reset();
        Store::cache()->failedReflection(Event::class);
        self::assertNull($this->invoke('reflectClass', $plugin, $io, $vendorDir, Event::class));
        self::assertTrue(FailedReflectionsStore::has(Event::class));

        $reflection = ReflectionClass::createFromName(Plugin::class);
        ReflectionsStore::add(Plugin::class, $reflection);
        self::assertSame($reflection, $this->invoke('reflectClass', $plugin, $io, $vendorDir, Plugin::class));
    }

    #[Test]
    public function tryResolveFromCacheReturnsNullWhenFilterOutcomeMissing(): void
    {
        $composer = $this->createComposer('broadcast', withCache: true);
        $plugin   = new Plugin();
        $io       = new TestIo();
        GenerativePluginExecutioner::execute($composer, $io, $plugin);
        Store::store($io);

        $className = Listener::class;
        $fileName  = Store::cache()->getClassAbsoluteFilePath($className);
        self::assertNotNull($fileName);
        self::assertTrue(Store::cache()->fileHashMatches($fileName));

        $filterOutcomes = new ReflectionProperty(Store::cache(), 'classFilterOutcome');
        $filterOutcomes->setValue(Store::cache(), []);

        $filters      = [...new Plugin()->filters()];
        $classFilters = [];
        foreach ($filters as $filter) {
            if (! ($filter instanceof ClassFilter)) {
                continue;
            }

            $classFilters[] = $filter;
        }

        $hashes = [];
        foreach ($classFilters as $classFilter) {
            $hashes[] = md5(serialize($classFilter));
        }

        self::assertNull($this->invoke(
            'tryResolveFromCache',
            $plugin,
            $className,
            $hashes,
            [...$plugin->collectors()],
        ));
    }

    #[Test]
    public function collectCachedItemsHandlesEmptyCollectorsAndMissingItems(): void
    {
        $this->createComposer('broadcast', withCache: true);
        $plugin = new ConfigurablePlugin([], []);

        self::assertSame([], $this->invoke('collectCachedItems', $plugin, Listener::class, []));

        $collector = new Collector();
        Store::cache()->collectedItems(ConfigurablePlugin::name(), Listener::class, $collector::class, ['x']);
        $collectedItems = new ReflectionProperty(Store::cache(), 'collectedItems');
        $data           = $collectedItems->getValue(Store::cache());
        self::assertIsArray($data);
        self::assertArrayHasKey(ConfigurablePlugin::name(), $data);
        self::assertIsArray($data[ConfigurablePlugin::name()]);
        self::assertArrayHasKey(Listener::class, $data[ConfigurablePlugin::name()]);
        self::assertIsArray($data[ConfigurablePlugin::name()][Listener::class]);
        $data[ConfigurablePlugin::name()][Listener::class][$collector::class] = null;
        $collectedItems->setValue(Store::cache(), $data);

        self::assertNull($this->invoke('collectCachedItems', $plugin, Listener::class, [$collector]));
    }

    #[Test]
    public function classFilterOutcomeReturnsCachedOutcomeWhenFileHashMatches(): void
    {
        $composer = $this->createComposer('broadcast', withCache: true);
        $plugin   = new Plugin();
        $io       = new TestIo();
        GenerativePluginExecutioner::execute($composer, $io, $plugin);

        $class      = ReflectionClass::createFromName(Listener::class);
        $filter     = new IsInstantiable();
        $filterHash = md5(serialize($filter));

        Store::cache()->classFilterOutcome($class->getName(), $filterHash, true);
        $fileName = $class->getFileName();
        self::assertNotNull($fileName);
        $hash = md5_file($fileName);
        self::assertNotFalse($hash);
        Store::cache()->fileHash($fileName, $hash);

        self::assertTrue($this->invoke('classFilterOutcome', $class, $filter, $filterHash));
    }

    #[Test]
    public function rememberClassFileIgnoresEmptyAndUnhashablePaths(): void
    {
        $this->createComposer('broadcast', withCache: true);

        $classFilePaths = new ReflectionProperty(Store::cache(), 'classFilePaths');
        $classFilePaths->setValue(Store::cache(), []);

        SuppressExpectedErrors::during(function (): void {
            $this->invoke('rememberClassFile', Listener::class, '');
            $this->invoke('rememberClassFile', Listener::class, $this->getTmpDir() . DIRECTORY_SEPARATOR . 'missing.php');
        });

        self::assertSame([], $classFilePaths->getValue(Store::cache()));
    }

    /** @return list<PackageInterface> */
    private function loadVendorDirPackages(string $vendorDir): array
    {
        $loaded = $this->invoke('loadVendorDirPackages', $vendorDir);
        if (! is_iterable($loaded)) {
            return [];
        }

        /** @var list<PackageInterface> $packages */
        $packages = [...$loaded];

        return $packages;
    }

    private function invoke(string $method, mixed ...$args): mixed
    {
        return new ReflectionMethod(GenerativePluginExecutioner::class, $method)->invoke(null, ...$args);
    }

    private function writeVendorComposerJson(string $vendorDir, string $packageName, string $contents): void
    {
        $dir = $vendorDir . DIRECTORY_SEPARATOR . $packageName;
        mkdir($dir, 0o755, true);
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'composer.json', $contents);
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
            $cacheFile = $this->cacheFilePath($app);
            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }

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
