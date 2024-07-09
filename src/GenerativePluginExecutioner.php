<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling;

use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Exception;
use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflector\DefaultReflector;
use Roave\BetterReflection\Reflector\Exception\IdentifierNotFound;
use Roave\BetterReflection\SourceLocator\Type\Composer\Factory\MakeLocatorForComposerJsonAndInstalledJson;
use Roave\BetterReflection\SourceLocator\Type\Composer\Psr\Exception\InvalidPrefixMapping;
use WyriHaximus\Lister;

use function array_key_exists;
use function count;
use function dirname;
use function explode;
use function file_exists;
use function is_dir;
use function is_file;
use function is_string;
use function rtrim;
use function Safe\mkdir;
use function sprintf;

use const DIRECTORY_SEPARATOR;

final class GenerativePluginExecutioner
{
    public static function execute(Composer $composer, IOInterface $io, GenerativePlugin $plugin): void
    {
        $start    = microtime(true);
        $vendorDir = $composer->getConfig()->get('vendor-dir');
        if (! is_string($vendorDir) || ! file_exists($vendorDir)) {
            throw new Exception('vendor-dir most be a string'); // @phpstan-ignore-line
        }

        $io->write('<info>' . $plugin::name() . ':</info> ' . $plugin::log(LogStages::Init));

        $packageFilters = $classFilters = [];
        foreach ($plugin->filters() as $filter) {
            if ($filter instanceof PackageFilter) {
                $packageFilters[] = $filter;
            }

            if ($filter instanceof ClassFilter) {
                $classFilters[] = $filter;
            }
        }

        $unfilteredPackages = self::autoloadablePackages(
            $composer->getPackage(),
            ...$composer->getRepositoryManager()->getLocalRepository()->getCanonicalPackages(),
        );
        $packages           =  [];
        foreach ($unfilteredPackages as $package) {
            foreach ($packageFilters as $packageFilter) {
                if (! $packageFilter($package)) {
                    continue;
                }

                $packages[] = $package;
            }
        }

        unset($unfilteredPackages);

        $unfilteredClasses = self::listClassesInPackages($plugin, $io, $vendorDir, ...$packages);
        $classes           =  [];
        foreach ($unfilteredClasses as $class) {
            foreach ($classFilters as $classFilter) {
                if (! $classFilter($class)) {
                    continue 2;
                }
            }
            $classes[] = $class;
        }

        $items = [];
        foreach ($classes as $class) {
            foreach ($plugin->collectors() as $collector) {
                $items = [...$items, ...$collector->collect($class)];
            }
        }

        $io->write('<info>' . $plugin::name() . ':</info> ' . sprintf($plugin::log(LogStages::Collected), count($items)));

        $plugin->compile(self::locateRootPackageInstallPath($plugin, $composer->getConfig(), $composer->getPackage()), ...$items);

        $io->write('<info>' . $plugin::name() . ':</info> ' . sprintf($plugin::log(LogStages::Completion), round(microtime(true) - $start, 2)));
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
        if (! is_string($vendorDir) || ! file_exists($vendorDir)) {
            throw new Exception('vendor-dir most be a string'); // @phpstan-ignore-line
        }

        // You're on your own
        if ($rootPackage->getName() === $plugin::name()) {
            return dirname($vendorDir);
        }

        return $vendorDir . '/' . $plugin::name();
    }

    /** @return iterable<PackageInterface> */
    private static function autoloadablePackages(PackageInterface ...$packages): iterable
    {
        foreach ($packages as $package) {
            if (count($package->getAutoload()) === 0) {
                continue;
            }

            if (! array_key_exists('classmap', $package->getAutoload()) && ! array_key_exists('psr-4', $package->getAutoload())) {
                continue;
            }

            yield $package;
        }
    }

    /** @return iterable<ReflectionClass> */
    private static function listClassesInPackages(GenerativePlugin $plugin, IOInterface $io, string $vendorDir, PackageInterface ...$packages): iterable
    {
        foreach ($packages as $package) {
            $packageName = $package->getName();
            $autoload    = $package->getAutoload();

            if (array_key_exists('psr-4', $autoload)) {
                foreach ($autoload['psr-4'] as $path) {
                    if (! is_string($path)) {
                        continue;
                    }

                    if ($package instanceof RootPackageInterface) {
                        yield from self::listReflectedClassesInPaths($plugin, $io, $vendorDir, dirname($vendorDir) . DIRECTORY_SEPARATOR . $path);

                        continue;
                    }

                    $fileName = rtrim($vendorDir . DIRECTORY_SEPARATOR . $packageName . DIRECTORY_SEPARATOR . $path, '/');
                    if (! file_exists($fileName)) {
                        continue;
                    }

                    yield from self::listReflectedClassesInPaths($plugin, $io, $vendorDir, $fileName);
                }
            }

            if (! array_key_exists('classmap', $autoload)) {
                continue;
            }

            foreach ($autoload['classmap'] as $path) {
                if ($package instanceof RootPackageInterface) {
                    yield from self::listReflectedClassesInPaths($plugin, $io, $vendorDir, dirname($vendorDir) . DIRECTORY_SEPARATOR . $path);
                }

                $fileName = rtrim($vendorDir . DIRECTORY_SEPARATOR . $packageName . DIRECTORY_SEPARATOR . $path, '/');
                if (! file_exists($fileName)) {
                    continue;
                }

                yield from self::listReflectedClassesInPaths($plugin, $io, $vendorDir, $fileName);
            }
        }
    }

    /** @return iterable<ReflectionClass> */
    private static function listReflectedClassesInPaths(GenerativePlugin $plugin, IOInterface $io, string $vendorDir, string $path): iterable
    {
        retry:
        try {
            $classReflector = new DefaultReflector(
                (new MakeLocatorForComposerJsonAndInstalledJson())(dirname($vendorDir), (new BetterReflection())->astLocator()),
            );
        } catch (InvalidPrefixMapping $invalidPrefixMapping) {
            mkdir(explode('" is not a', explode('" for prefix "', $invalidPrefixMapping->getMessage())[1])[0]);
            goto retry;
        }

        foreach (self::listClassesInPaths($path) as $class) {
            try {
                /** @phpstan-ignore-next-line */
                yield (static function (ReflectionClass $reflectionClass): ReflectionClass {
                    /**
                     * Unit tests will fail if this line isn't here, getMethods will also do the trick
                     * Assuming any actual class properties reading will trigger it to be loaded
                     * Which will unit tests cause to succeed and not complain about
                     * WyriHaximus\Broadcast\Generated\AbstractListenerProvider not being found
                     *
                     * @psalm-suppress UnusedMethodCall
                     */
                    $reflectionClass->getInterfaces();

                    return $reflectionClass;
                })($classReflector->reflectClass($class));
            } catch (IdentifierNotFound $identifierNotFound) {
                $io->write(sprintf(
                    '<error>' . $plugin::name() . ':</error> ' . $plugin::log(LogStages::Error),
                    sprintf(
                        'Cannot reflect "<fg=cyan>%s</>": <fg=yellow>%s</>',
                        $class,
                        $identifierNotFound->getMessage(),
                    ),
                ));
            }
        }
    }

    /** @return iterable<string> */
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
}
