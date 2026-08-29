<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling\Composer;

use Composer\Composer;
use Composer\Config;
use Composer\Package\RootPackage;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use WyriHaximus\Composer\GenerativePluginTooling\Cache\CacheFilePath;
use WyriHaximus\Composer\GenerativePluginTooling\Composer\CacheLocator;
use WyriHaximus\TestUtilities\TestCase;

use function dirname;

use const DIRECTORY_SEPARATOR;

final class CacheLocatorTest extends TestCase
{
    #[Before]
    public function resetLocator(): void
    {
        $instance = new ReflectionProperty(CacheLocator::class, 'instance');
        $instance->setValue(null, null);
    }

    /** @return iterable<string, array{0: array<mixed>, 1: string}> */
    public static function provideExtras(): iterable
    {
        yield 'empty extra' => [[], ''];

        yield 'wyrihaximus is not an array' => [['wyrihaximus' => 'nope'], ''];

        yield 'missing tooling key' => [['wyrihaximus' => ['broadcast' => true]], ''];

        yield 'tooling is not an array' => [
            ['wyrihaximus' => ['generative-composer-plugin-tooling' => 'nope']],
            '',
        ];

        yield 'missing cache key' => [
            ['wyrihaximus' => ['generative-composer-plugin-tooling' => ['other' => true]]],
            '',
        ];

        yield 'cache is not a string' => [
            ['wyrihaximus' => ['generative-composer-plugin-tooling' => ['cache' => ['nested']]]],
            '',
        ];

        yield 'cache configured' => [
            ['wyrihaximus' => ['generative-composer-plugin-tooling' => ['cache' => 'var/cache.json']]],
            'var/cache.json',
        ];
    }

    /** @param array<mixed> $extra */
    #[Test]
    #[DataProvider('provideExtras')]
    public function locateResolvesCacheFilePathFromExtra(array $extra, string $expectedCacheLocation): void
    {
        $vendorDir = $this->getTmpDir() . 'vendor' . DIRECTORY_SEPARATOR;
        $composer  = $this->createComposer($vendorDir, $extra);

        $located = CacheLocator::locate($composer);

        if ($expectedCacheLocation === '') {
            self::assertNull($located);

            return;
        }

        self::assertInstanceOf(CacheFilePath::class, $located);
        self::assertSame(
            dirname($vendorDir) . DIRECTORY_SEPARATOR . $expectedCacheLocation,
            (string) $located,
        );
        self::assertSame($expectedCacheLocation, $located->cacheLocation);
    }

    #[Test]
    public function locateReusesSingletonInstance(): void
    {
        $composer = $this->createComposer(
            $this->getTmpDir() . 'vendor' . DIRECTORY_SEPARATOR,
            [
                'wyrihaximus' => [
                    'generative-composer-plugin-tooling' => ['cache' => 'var/first.json'],
                ],
            ],
        );

        $first  = CacheLocator::locate($composer);
        $second = CacheLocator::locate($this->createComposer(
            $this->getTmpDir() . 'other-vendor' . DIRECTORY_SEPARATOR,
            [],
        ));

        self::assertSame($first, $second);
        self::assertInstanceOf(CacheFilePath::class, $second);
        self::assertSame('var/first.json', $second->cacheLocation);
    }

    #[Test]
    public function constructParsesExtraDirectly(): void
    {
        $vendorDir = $this->getTmpDir() . 'vendor' . DIRECTORY_SEPARATOR;
        $package   = new RootPackage('example/package', '1.0.0', '1.0.0');
        $package->setExtra([
            'wyrihaximus' => [
                'generative-composer-plugin-tooling' => ['cache' => 'var/direct.json'],
            ],
        ]);

        $locator       = new CacheLocator($vendorDir, $package);
        $cacheLocation = new ReflectionProperty(CacheLocator::class, 'cacheLocation');

        self::assertInstanceOf(CacheFilePath::class, $cacheLocation->getValue($locator));
        self::assertSame(
            dirname($vendorDir) . DIRECTORY_SEPARATOR . 'var/direct.json',
            (string) $cacheLocation->getValue($locator),
        );
    }

    /** @param array<mixed> $extra */
    private function createComposer(string $vendorDir, array $extra): Composer
    {
        $config = new Config();
        $config->merge(['config' => ['vendor-dir' => $vendorDir]]);

        $package = new RootPackage('example/package', '1.0.0', '1.0.0');
        $package->setExtra($extra);

        $composer = new Composer();
        $composer->setConfig($config);
        $composer->setPackage($package);

        return $composer;
    }
}
