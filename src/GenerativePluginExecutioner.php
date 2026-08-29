<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling;

use Composer\Composer;
use Composer\Config;
use Composer\InstalledVersions;
use Composer\IO\IOInterface;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\Loader\JsonLoader;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use FilesystemIterator;
use GlobIterator;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflector\DefaultReflector;
use Roave\BetterReflection\Reflector\Exception\IdentifierNotFound;
use Roave\BetterReflection\SourceLocator\Type\Composer\Factory\MakeLocatorForComposerJsonAndInstalledJson;
use Roave\BetterReflection\SourceLocator\Type\Composer\Psr\Exception\InvalidPrefixMapping;
use RuntimeException;
use SplFileInfo;
use WyriHaximus\Composer\GenerativePluginTooling\Cache\Store;
use WyriHaximus\Composer\GenerativePluginTooling\Composer\ASTLocatorStore;
use WyriHaximus\Composer\GenerativePluginTooling\Helper\ItemSerializer;
use WyriHaximus\Lister;

use function array_key_exists;
use function array_map;
use function array_values;
use function assert;
use function count;
use function dirname;
use function file_exists;
use function file_get_contents;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function json_decode;
use function json_encode;
use function md5;
use function md5_file;
use function microtime;
use function mkdir;
use function preg_match;
use function round;
use function rtrim;
use function serialize;
use function spl_autoload_register;
use function spl_autoload_unregister;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

use const DIRECTORY_SEPARATOR;

/** @api */
final class GenerativePluginExecutioner
{
    public static function execute(Composer $composer, IOInterface $io, GenerativePlugin $plugin): void
    {
        $start     = microtime(true);
        $vendorDir = self::getVendorDir($composer);

        $io->write('<info>' . $plugin::name() . ':</info> ' . $plugin::log(LogStages::Init));

        $packageFilters = $classFilters = [];
        foreach ($plugin->filters() as $filter) {
            if ($filter instanceof PackageFilter) {
                $packageFilters[] = $filter;
            }

            if (! ($filter instanceof ClassFilter)) {
                continue;
            }

            $classFilters[] = $filter;
        }

        $classFilterKeyHashes = array_map(
            static fn (ClassFilter $classFilter): string => md5(serialize($classFilter)),
            $classFilters,
        );
        $collectors           = array_values([...$plugin->collectors()]);

        $unfilteredPackages = [
            ...self::autoloadablePackages(
                $composer->getPackage(),
                ...self::loadVendorDirPackages($vendorDir),
            ),
        ];

        $autoloader = self::createAutoloader($vendorDir, ...$unfilteredPackages);
        spl_autoload_register($autoloader);

        $packages =  [];
        foreach ($unfilteredPackages as $package) {
            foreach ($packageFilters as $packageFilter) {
                if (! $packageFilter($package)) {
                    continue;
                }

                $packages[] = $package;
            }
        }

        unset($unfilteredPackages);

        $items           = [];
        $needsReflection = [];
        foreach (self::listClassNamesInPackages($vendorDir, ...$packages) as $className) {
            /** @var class-string $className */
            $cachedItems = self::tryResolveFromCache($plugin, $className, $classFilterKeyHashes, $collectors);
            if ($cachedItems === false) {
                continue;
            }

            if ($cachedItems !== null) {
                $items = [...$items, ...$cachedItems];
                continue;
            }

            $needsReflection[] = $className;
        }

        foreach ($needsReflection as $className) {
            /** @var class-string $className */
            $class = self::reflectClass($plugin, $io, $vendorDir, $className);
            if (! $class instanceof ReflectionClass) {
                continue;
            }

            foreach ($classFilters as $index => $classFilter) {
                if (! self::classFilterOutcome($class, $classFilter, $classFilterKeyHashes[$index])) {
                    continue 2;
                }
            }

            $cachedItems = self::collectCachedItems($plugin, $class->getName(), $collectors);
            if ($cachedItems !== null) {
                $items = [...$items, ...$cachedItems];
                continue;
            }

            foreach ($collectors as $collector) {
                $collected = [...$collector->collect($class)];
                self::storeCollectedItems($plugin, $class, $collector, ...$collected);
                $items = [...$items, ...$collected];
            }
        }

        $io->write('<info>' . $plugin::name() . ':</info> ' . sprintf($plugin::log(LogStages::Collected), count($items)));

        $plugin->compile(self::locateRootPackageInstallPath($plugin, $composer->getConfig(), $composer->getPackage()), ...$items);

        $io->write('<info>' . $plugin::name() . ':</info> ' . sprintf($plugin::log(LogStages::Completion), round(microtime(true) - $start, 2)));

        spl_autoload_unregister($autoloader);
    }

    /**
     * Find the location where to put the generate PHP class in.
     */
    private static function locateRootPackageInstallPath(
        GenerativePlugin $plugin,
        Config $composerConfig,
        RootPackageInterface $rootPackage,
    ): string {
        $vendorDir = $composerConfig->get('vendor-dir');
        if (! file_exists($vendorDir)) {
            throw new RuntimeException('vendor-dir most be a string');
        }

        // You're on your own
        if ($rootPackage->getName() === $plugin::name()) {
            return dirname($vendorDir);
        }

        return $vendorDir . '/' . $plugin::name();
    }

    /** @return iterable<string, PackageInterface> */
    private static function autoloadablePackages(PackageInterface ...$packages): iterable
    {
        foreach ($packages as $package) {
            if (count($package->getAutoload()) === 0) {
                continue;
            }

            if (! array_key_exists('classmap', $package->getAutoload()) && ! array_key_exists('psr-4', $package->getAutoload())) {
                continue;
            }

            yield $package->getName() => $package;
        }
    }

    /**
     * @param non-empty-string $vendorDir
     *
     * @return iterable<class-string>
     */
    private static function listClassNamesInPackages(string $vendorDir, PackageInterface ...$packages): iterable
    {
        foreach ($packages as $package) {
            foreach (self::autoloadPaths($vendorDir, $package) as $path) {
                /** @var class-string $class */
                foreach (self::listClassesInPaths($path) as $class) {
                    yield $class;
                }
            }
        }
    }

    /**
     * @param non-empty-string $vendorDir
     *
     * @return iterable<non-empty-string>
     */
    private static function autoloadPaths(string $vendorDir, PackageInterface $package): iterable
    {
        $autoload = $package->getAutoload();

        if (array_key_exists('psr-4', $autoload)) {
            foreach ($autoload['psr-4'] as $path) {
                if (! is_string($path)) {
                    continue;
                }

                if ($package instanceof RootPackageInterface) {
                    yield self::normalizePath(dirname($vendorDir) . DIRECTORY_SEPARATOR . $path);

                    continue;
                }

                $fileName = self::packagePath($vendorDir, $package, $path);
                if ($fileName === '' || ! file_exists($fileName)) {
                    continue;
                }

                yield $fileName;
            }
        }

        if (! array_key_exists('classmap', $autoload)) {
            return;
        }

        /** @var non-empty-string $path */ // phpcs:disable
        foreach ($autoload['classmap'] as $path) {
            if ($package instanceof RootPackageInterface) {
                $normalizedPath = self::normalizePath($path);
                $pathPrefix     = dirname($vendorDir) . DIRECTORY_SEPARATOR;
                if (str_starts_with($normalizedPath, $pathPrefix)) {
                    $pathPrefix = '';
                }

                yield self::normalizePath($pathPrefix . $normalizedPath);
            }

            $fileName = self::packagePath($vendorDir, $package, $path);
            if ($fileName !== '' && file_exists($fileName)) {
                yield $fileName;
            }
        }
    }

    private static function packagePath(string $vendorDir, PackageInterface $package, string $path): string
    {
        return self::normalizePath(
            $vendorDir
            . DIRECTORY_SEPARATOR
            . $package->getName()
            . DIRECTORY_SEPARATOR
            . $path,
        );
    }

    /** @return non-empty-string */
    private static function normalizePath(string $path): string
    {
        /** @var non-empty-string $normalized */
        $normalized = rtrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path), '/\\');

        return $normalized;
    }

    /**
     * @param non-empty-string $vendorDir
     * @param class-string     $class
     */
    private static function reflectClass(GenerativePlugin $plugin, IOInterface $io, string $vendorDir, string $class): ReflectionClass|null
    {
        if (FailedReflectionsStore::has($class)) {
            return null;
        }

        if (Store::cache()->hasFailedReflection($class)) {
            FailedReflectionsStore::add($class);

            return null;
        }

        if (ReflectionsStore::has($class)) {
            return ReflectionsStore::get($class);
        }

        try {
            $reflection = (static function (ReflectionClass $reflectionClass): ReflectionClass {
                /**
                 * Unit tests will fail if this line isn't here, getMethods will also do the trick
                 * Assuming any actual class properties reading will trigger it to be loaded
                 * Which will unit tests cause to succeed and not complain about
                 * WyriHaximus\Broadcast\Generated\AbstractListenerProvider not being found
                 */
                $reflectionClass->getInterfaces();

                return $reflectionClass;
            })(self::createClassReflector($vendorDir)->reflectClass($class));
            ReflectionsStore::add($class, $reflection);
            self::rememberClassFile($class, $reflection->getFileName() ?? '');

            return $reflection;
        } catch (IdentifierNotFound $identifierNotFound) {
            FailedReflectionsStore::add($class);
            Store::cache()->failedReflection($class);

            $io->write(sprintf(
                '<error>' . $plugin::name() . ':</error> ' . $plugin::log(LogStages::Error),
                sprintf(
                    'Cannot reflect "<fg=cyan>%s</>": <fg=yellow>%s</>',
                    $class,
                    $identifierNotFound->getMessage(),
                ),
            ));

            return null;
        }
    }

    /**
     * @param non-empty-string $path
     *
     * @return iterable<string>
     */
    private static function listClassesInPaths(string $path): iterable
    {
        if (is_dir($path)) {
            yield from Lister::classesInDirectories($path);
        }

        if (! is_file($path)) {
            return;
        }

        yield from Lister::classesInFiles($path);
    }

    /**
     * @param non-empty-string $vendorDir
     *
     * @return iterable<PackageInterface>
     */
    private static function loadVendorDirPackages(string $vendorDir): iterable
    {
        $loader = new JsonLoader(new ArrayLoader());

        foreach (new GlobIterator($vendorDir . '/*/*/composer.json', FilesystemIterator::KEY_AS_FILENAME | FilesystemIterator::SKIP_DOTS) as $node) {
            assert($node instanceof SplFileInfo);
            $realPath = $node->getRealPath();
            if (! is_string($realPath) || ! is_file($realPath)) {
                continue;
            }

            $composerJson = file_get_contents($realPath);
            if ($composerJson === false) { // @codeCoverageIgnore
                continue; // @codeCoverageIgnore
            }

            $json = json_decode($composerJson, true);
            if (! is_array($json)) {
                continue;
            }

            if (! array_key_exists('name', $json)) {
                continue;
            }

            if (! is_string($json['name'])) {
                continue;
            }

            $json['version'] = InstalledVersions::getVersion($json['name']);

            $jsonString = json_encode($json);

            if (! is_string($jsonString)) {
                continue;
            }

            yield $loader->load($jsonString);
        }
    }

    /** @param non-empty-string $vendorDir */
    private static function createClassReflector(string $vendorDir): DefaultReflector
    {
        if (ClassReflectorStore::has($vendorDir)) {
            return ClassReflectorStore::get($vendorDir);
        }

        try {
            return self::buildClassReflector($vendorDir);
        } catch (InvalidPrefixMapping $invalidPrefixMapping) {
            $path = self::missingAutoloadDirectoryFromException($invalidPrefixMapping);
            if (! file_exists($path)) {
                mkdir($path, recursive: true);
            }

            return self::buildClassReflector($vendorDir);
        }
    }

    /** @param non-empty-string $vendorDir */
    private static function buildClassReflector(string $vendorDir): DefaultReflector
    {
        $reflector = new DefaultReflector(
            (new MakeLocatorForComposerJsonAndInstalledJson())(dirname($vendorDir), ASTLocatorStore::ASTLocator()),
        );

        ClassReflectorStore::add($vendorDir, $reflector);

        return $reflector;
    }

    /** @return non-empty-string */
    private static function missingAutoloadDirectoryFromException(InvalidPrefixMapping $invalidPrefixMapping): string
    {
        if (preg_match('/ for prefix "(.*)" is not a directory$/', $invalidPrefixMapping->getMessage(), $matches) !== 1) {
            throw $invalidPrefixMapping;
        }

        /** @var non-empty-string $path */
        $path = $matches[1];

        return $path;
    }

    /** @return non-empty-string */
    private static function getVendorDir(Composer $composer): string
    {
        $vendorDir = $composer->getConfig()->get('vendor-dir');
        if ($vendorDir === '' || ! file_exists($vendorDir)) {
            throw new RuntimeException('vendor-dir most be a string');
        }

        return $vendorDir;
    }

    /**
     * @return callable(string): void
     *
     * @infection-ignore-all
     */
    private static function createAutoloader(string $vendorDir, PackageInterface ...$packages): callable
    {
        return static function (string $class) use ($vendorDir, $packages): void {
            if (!str_contains($class, '\\')) {
                return;
            }

            foreach ($packages as $package) {
                $autoload = $package->getAutoload();
                if (! array_key_exists('psr-4', $autoload)) {
                    continue;
                }

                foreach ($autoload['psr-4'] as $namespace => $paths) {
                    foreach (is_array($paths) ? $paths : [$paths] as $path) {
                        if (! str_starts_with($class, $namespace)) {
                            continue;
                        }

                        $possibleFilePath  = $vendorDir . DIRECTORY_SEPARATOR;
                        $possibleFilePath .= $package->getName() . DIRECTORY_SEPARATOR;
                        $possibleFilePath .= $path;
                        $possibleFilePath .= substr($class, strlen($namespace));
                        $possibleFilePath .= '.php';
                        $possibleFilePath  = self::normalizePath($possibleFilePath);

                        if (! file_exists($possibleFilePath)) {
                            continue;
                        }

                        require_once $possibleFilePath;
                    }
                }
            }
        };
    }

    /**
     * @param class-string        $className
     * @param list<string>        $classFilterKeyHashes
     * @param list<ItemCollector> $collectors
     *
     * @return array<Item>|false|null
     */
    private static function tryResolveFromCache(
        GenerativePlugin $plugin,
        string $className,
        array $classFilterKeyHashes,
        array $collectors,
    ): array|false|null {
        if (FailedReflectionsStore::has($className) || Store::cache()->hasFailedReflection($className)) {
            if (Store::cache()->hasFailedReflection($className)) {
                FailedReflectionsStore::add($className);
            }

            return false;
        }

        $fileName = Store::cache()->getClassAbsoluteFilePath($className);
        if ($fileName === null || ! Store::cache()->fileHashMatches($fileName)) {
            return null;
        }

        foreach ($classFilterKeyHashes as $filterKeyHash) {
            $outcome = Store::cache()->getClassFilterOutcome($className, $filterKeyHash);
            if ($outcome === null) {
                return null;
            }

            if (! $outcome) {
                return false;
            }
        }

        return self::collectCachedItems($plugin, $className, $collectors);
    }

    /**
     * @param class-string        $className
     * @param list<ItemCollector> $collectors
     *
     * @return array<Item>|null
     */
    private static function collectCachedItems(GenerativePlugin $plugin, string $className, array $collectors): array|null
    {
        if ($collectors === []) {
            return [];
        }

        if (! Store::cache()->hasCollectedItemsForClass($plugin::name(), $className, $collectors)) {
            return null;
        }

        $items = [];
        foreach ($collectors as $collector) {
            $cachedItems = Store::cache()->getCollectedItems($plugin::name(), $className, $collector::class);
            if ($cachedItems === null) {
                return null;
            }

            foreach ($cachedItems as $serializedItem) {
                $items[] = ItemSerializer::unserialize($serializedItem);
            }
        }

        return $items;
    }

    private static function classFilterOutcome(ReflectionClass $class, ClassFilter $classFilter, string $filterKeyHash): bool
    {
        $fileName      = $class->getFileName();
        $cachedOutcome = Store::cache()->getClassFilterOutcome($class->getName(), $filterKeyHash);
        if ($cachedOutcome !== null && $fileName !== null && Store::cache()->fileHashMatches($fileName)) {
            return $cachedOutcome;
        }

        $classFilterOutcome = $classFilter($class);
        Store::cache()->classFilterOutcome($class->getName(), $filterKeyHash, $classFilterOutcome);
        self::rememberClassFile($class->getName(), $fileName ?? '');

        return $classFilterOutcome;
    }

    private static function storeCollectedItems(
        GenerativePlugin $plugin,
        ReflectionClass $class,
        ItemCollector $collector,
        Item ...$collected,
    ): void {
        $serializedItems = [];
        foreach ($collected as $item) {
            $serializedItems[] = ItemSerializer::serialize($item);
        }

        Store::cache()->collectedItems($plugin::name(), $class->getName(), $collector::class, $serializedItems);
        self::rememberClassFile($class->getName(), $class->getFileName() ?? '');
    }

    /** @param class-string $class */
    private static function rememberClassFile(string $class, string $fileName): void
    {
        if ($fileName === '') {
            return;
        }

        $hash = md5_file($fileName);
        if ($hash === false) {
            return;
        }

        Store::cache()->fileHash($fileName, $hash);
        Store::cache()->classFilePath($class, $fileName);
    }
}
