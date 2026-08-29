<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling;

use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use WyriHaximus\Composer\GenerativePluginTooling\Cache;
use WyriHaximus\Composer\GenerativePluginTooling\Helper\ItemSerializer;
use WyriHaximus\Tests\Composer\GenerativePluginTooling\Support\FilesystemFixtures;
use WyriHaximus\Tests\Composer\GenerativePluginTooling\Support\SuppressExpectedErrors;
use WyriHaximus\TestUtilities\TestCase;

use function dirname;
use function file_put_contents;
use function ksort;
use function md5;
use function md5_file;
use function str_replace;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const DIRECTORY_SEPARATOR;

final class CacheTest extends TestCase
{
    #[Test]
    public function disabledCacheIsInert(): void
    {
        $cache = Cache::disabled();

        self::assertFalse($cache->isEnabled());
        self::assertFalse($cache->fileHashMatches(__FILE__));
        self::assertNull($cache->getClassFilterOutcome(self::class, self::class));
        self::assertFalse($cache->hasFailedReflection(self::class));
        self::assertFalse($cache->installedJsonHashMatches(__FILE__));
        self::assertNull($cache->getClassAbsoluteFilePath(self::class));
        self::assertNull($cache->getCollectedItems('plugin/name', self::class, Collector::class));
        self::assertFalse($cache->hasCollectedItems('plugin/name', self::class, Collector::class));
        self::assertFalse($cache->hasCollectedItemsForClass('plugin/name', self::class, [new Collector()]));

        $cache->fileHash(__FILE__, 'hash');
        $cache->classFilterOutcome(self::class, self::class, true);
        $cache->failedReflection(self::class);
        $cache->classFilePath(self::class, __FILE__);
        $cache->classFilePath(self::class, '');
        $cache->setInstalledJsonHash(__FILE__);
        $cache->collectedItems('plugin/name', self::class, self::class, [ItemSerializer::serialize(new Item('event', self::class, 'method', false, false))]);

        self::assertSame([
            'classFilePaths' => [],
            'classFilterOutcome' => [],
            'collectedItems' => [],
            'failedReflections' => [],
            'fileHashes' => [],
            'installedJsonHash' => '',
        ], $cache->jsonSerialize());
    }

    #[Test]
    public function fromJSONCreatesEnabledCache(): void
    {
        self::assertSame([
            'classFilePaths' => [],
            'classFilterOutcome' => [],
            'collectedItems' => [],
            'failedReflections' => [],
            'fileHashes' => [],
            'installedJsonHash' => '',
        ], Cache::fromJSON([], '')->jsonSerialize());

        $cache = Cache::fromJSON([], '');

        self::assertTrue($cache->isEnabled());
        $cache->fileHash(__FILE__, 'hash');
        self::assertTrue($cache->fileHashMatches(__FILE__));
        self::assertSame([__FILE__ => 'hash'], $cache->jsonSerialize()['fileHashes']);
    }

    #[Test]
    public function disabledCacheIgnoresPopulatedState(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'cache-disabled-state-');
        self::assertNotFalse($file);
        file_put_contents($file, 'contents');

        $hash = md5_file($file);
        self::assertNotFalse($hash);

        $cache = new Cache(
            '',
            [$file => $hash],
            [md5(self::class . '_=_filter') => true],
            [self::class => true],
            ['plugin/name' => [self::class => [Collector::class => ['item']]]],
            [self::class => $file],
            $hash,
            false,
            [],
        );

        self::assertFalse($cache->isEnabled());
        self::assertFalse($cache->installedJsonHashMatches($file));
        self::assertFalse($cache->fileHashMatches($file));
        self::assertNull($cache->getClassFilterOutcome(self::class, 'filter'));
        self::assertFalse($cache->hasFailedReflection(self::class));
        self::assertNull($cache->getClassAbsoluteFilePath(self::class));
        self::assertNull($cache->getCollectedItems('plugin/name', self::class, Collector::class));
        self::assertFalse($cache->hasCollectedItems('plugin/name', self::class, Collector::class));
        self::assertFalse($cache->hasCollectedItemsForClass('plugin/name', self::class, [new Collector()]));
        self::assertFalse($cache->hasCollectedItemsForClass('plugin/name', self::class, []));

        unlink($file);
    }

    #[Test]
    public function disabledCacheHasFailedReflectionIgnoresStoredClasses(): void
    {
        $cache = new Cache('', [], [], [self::class => true], [], [], '', false, []);

        self::assertFalse($cache->hasFailedReflection(self::class));
    }

    #[Test]
    public function fileHashMatchesUsesMemoOverRecomputedHash(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'cache-memo-');
        self::assertNotFalse($file);
        file_put_contents($file, 'contents');

        $root  = dirname($file) . DIRECTORY_SEPARATOR;
        $cache = Cache::fromJSON([], $root);
        $hash  = md5_file($file);
        self::assertNotFalse($hash);
        $cache->fileHash($file, $hash);

        self::assertTrue($cache->fileHashMatches($file));

        $fileHashes = new ReflectionProperty($cache, 'fileHashes');
        $fileHashes->setValue($cache, []);

        self::assertTrue($cache->fileHashMatches($file));

        unlink($file);
    }

    #[Test]
    public function jsonSerializeSortsStringKeysAtAllLevels(): void
    {
        $cache = Cache::fromJSON([], '');
        $cache->fileHash('z-file.php', 'hash-z');
        $cache->fileHash('a-file.php', 'hash-a');
        $cache->classFilterOutcome(Item::class, 'filter-b', true);
        $cache->classFilterOutcome(Collector::class, 'filter-a', false);
        $cache->classFilePath(Item::class, 'z-file.php');
        $cache->classFilePath(Collector::class, 'a-file.php');
        $cache->failedReflection(Item::class);
        $cache->failedReflection(Collector::class);
        $cache->collectedItems('plugin/z', Item::class, Item::class, ['item-b']);
        $cache->collectedItems('plugin/a', Collector::class, Collector::class, ['item-a']);
        $cache->collectedItems('plugin/a', Collector::class, Item::class, ['item-z']);
        $cache->collectedItems('plugin/a', Item::class, Collector::class, ['item-nested']);

        $filterOutcomes = [
            md5(Collector::class . '_=_filter-a') => false,
            md5(Item::class . '_=_filter-b') => true,
        ];
        ksort($filterOutcomes);

        self::assertSame([
            'classFilePaths' => [
                Collector::class => 'a-file.php',
                Item::class => 'z-file.php',
            ],
            'classFilterOutcome' => $filterOutcomes,
            'collectedItems' => [
                'plugin/a' => [
                    Collector::class => [
                        Collector::class => ['item-a'],
                        Item::class => ['item-z'],
                    ],
                    Item::class => [
                        Collector::class => ['item-nested'],
                    ],
                ],
                'plugin/z' => [
                    Item::class => [
                        Item::class => ['item-b'],
                    ],
                ],
            ],
            'failedReflections' => [Item::class, Collector::class],
            'fileHashes' => [
                'a-file.php' => 'hash-a',
                'z-file.php' => 'hash-z',
            ],
            'installedJsonHash' => '',
        ], $cache->jsonSerialize());
    }

    #[Test]
    public function fileHashMatchesUsesRelativePaths(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'cache-test-');
        self::assertNotFalse($file);
        file_put_contents($file, 'contents');

        $root  = dirname($file) . DIRECTORY_SEPARATOR;
        $cache = Cache::fromJSON([], $root);
        $hash  = md5_file($file);
        self::assertNotFalse($hash);
        $cache->fileHash($file, $hash);

        self::assertTrue($cache->fileHashMatches($file));
        self::assertTrue($cache->fileHashMatches($file));

        unlink($file);
    }

    #[Test]
    public function getCollectedItemsReturnsNullWhenDisabledEvenWithData(): void
    {
        $cache = new Cache(
            '',
            [],
            [],
            [],
            ['plugin/name' => [self::class => [Collector::class => ['item']]]],
            [],
            '',
            false,
            [],
        );

        self::assertNull($cache->getCollectedItems('plugin/name', self::class, Collector::class));
    }

    #[Test]
    public function relativePathWithEmptyRootKeepsAbsoluteFileHashKeys(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'cache-empty-root-');
        self::assertNotFalse($file);
        file_put_contents($file, 'contents');

        $cache = Cache::fromJSON([], '');
        $hash  = md5_file($file);
        self::assertNotFalse($hash);
        $cache->fileHash($file, $hash);

        self::assertSame([$file => $hash], $cache->jsonSerialize()['fileHashes']);
        self::assertTrue($cache->fileHashMatches($file));

        unlink($file);
    }

    #[Test]
    public function classFilePathRoundTrips(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'cache-test-');
        self::assertNotFalse($file);

        $root  = dirname($file) . DIRECTORY_SEPARATOR;
        $cache = Cache::fromJSON([], $root);
        $cache->classFilePath(self::class, $file);

        self::assertSame($file, $cache->getClassAbsoluteFilePath(self::class));

        unlink($file);
    }

    #[Test]
    public function classFilePathRoundTripsWithMixedDirectorySeparators(): void
    {
        $root = 'C:\\Users\\example\\AppData\\Local\\Temp/';
        $file = 'C:\\Users\\example\\AppData\\Local\\Temp\\cache-test.tmp';

        $cache = Cache::fromJSON([], $root);
        $cache->classFilePath(self::class, $file);

        self::assertSame(
            str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $file),
            $cache->getClassAbsoluteFilePath(self::class),
        );
    }

    #[Test]
    public function legacyClassFilterOutcomeEntriesAreMigrated(): void
    {
        $serializedFilter = 'O:8:"stdClass":0:{}';
        $filterKeyHash    = md5($serializedFilter);

        $cache = Cache::fromJSON([
            'classFilterOutcome' => [
                md5('WyriHaximus\\Foo_=_' . $serializedFilter) => [
                    'class' => 'WyriHaximus\\Foo',
                    'filterKey' => $serializedFilter,
                    'outcome' => true,
                ],
            ],
        ], '');

        self::assertTrue($cache->getClassFilterOutcome('WyriHaximus\\Foo', $filterKeyHash));
    }

    #[Test]
    public function collectedItemsRoundTrip(): void
    {
        $cache = Cache::fromJSON([], '');
        $item  = new Item('WyriHaximus\\Event', self::class, 'handle', false, false);

        $cache->collectedItems('wyrihaximus/broadcast', self::class, Collector::class, [ItemSerializer::serialize($item)]);

        $cachedItems = $cache->getCollectedItems('wyrihaximus/broadcast', self::class, Collector::class);
        self::assertNotNull($cachedItems);
        self::assertSame([ItemSerializer::serialize($item)], $cachedItems);
        self::assertTrue($cache->hasCollectedItemsForClass('wyrihaximus/broadcast', self::class, [new Collector()]));
        self::assertEquals($item, ItemSerializer::unserialize($cachedItems[0]));
    }

    #[Test]
    public function emptyCollectedItemsAreCached(): void
    {
        $cache = Cache::fromJSON([], '');

        $cache->collectedItems('wyrihaximus/broadcast', 'WyriHaximus\\Foo', Collector::class, []);

        self::assertSame([], $cache->getCollectedItems('wyrihaximus/broadcast', 'WyriHaximus\\Foo', Collector::class));
        self::assertTrue($cache->hasCollectedItems('wyrihaximus/broadcast', 'WyriHaximus\\Foo', Collector::class));
    }

    #[Test]
    public function fromJSONSkipsInvalidClassFilterOutcomeEntries(): void
    {
        $validBoolKey = md5('WyriHaximus\\Bool_=_filter');
        $legacyKey    = md5('WyriHaximus\\Legacy_=_legacy-filter');

        $cache = Cache::fromJSON([
            'classFilterOutcome' => [
                0 => true,
                $validBoolKey => true,
                'invalid-string' => 'nope',
                'invalid-missing-outcome' => ['class' => 'WyriHaximus\\Foo'],
                'invalid-missing-class' => ['outcome' => true],
                'invalid-outcome-type' => [
                    'class' => 'WyriHaximus\\Foo',
                    'outcome' => 'yes',
                ],
                'invalid-class-type' => [
                    'class' => 123,
                    'outcome' => true,
                ],
                $legacyKey => [
                    'class' => 'WyriHaximus\\Legacy',
                    'outcome' => false,
                ],
            ],
            'installedJsonHash' => 123,
        ], '');

        self::assertTrue($cache->getClassFilterOutcome('WyriHaximus\\Bool', 'filter'));

        $serialized = $cache->jsonSerialize();
        self::assertIsArray($serialized['classFilterOutcome']);
        self::assertFalse($serialized['classFilterOutcome'][$legacyKey]);
        self::assertNull($cache->getClassFilterOutcome('WyriHaximus\\Missing', 'filter'));
        self::assertSame('', $serialized['installedJsonHash']);
    }

    #[Test]
    public function installedJsonHashMatchesReturnsFalseForEmptyHash(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'cache-installed-');
        self::assertNotFalse($file);
        file_put_contents($file, 'installed');

        $cache = Cache::fromJSON(['installedJsonHash' => ''], '');
        self::assertFalse($cache->installedJsonHashMatches($file));

        unlink($file);
    }

    #[Test]
    public function installedJsonHashMatchesReturnsFalseForMissingFile(): void
    {
        $cache = Cache::fromJSON(['installedJsonHash' => 'deadbeef'], '');
        self::assertFalse($cache->installedJsonHashMatches(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cache-missing-installed.json'));
    }

    #[Test]
    public function installedJsonHashMatchesComparesFileHash(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'cache-installed-');
        self::assertNotFalse($file);
        file_put_contents($file, 'installed');

        $hash = md5_file($file);
        self::assertNotFalse($hash);
        $cache = Cache::fromJSON(['installedJsonHash' => $hash], '');

        self::assertTrue($cache->installedJsonHashMatches($file));

        file_put_contents($file, 'changed');
        self::assertFalse($cache->installedJsonHashMatches($file));

        unlink($file);
    }

    #[Test]
    public function setInstalledJsonHashUpdatesFromFile(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'cache-installed-');
        self::assertNotFalse($file);
        file_put_contents($file, 'installed');

        $cache = Cache::fromJSON([], '');
        $cache->setInstalledJsonHash($file);

        $hash = md5_file($file);
        self::assertNotFalse($hash);
        self::assertSame($hash, $cache->jsonSerialize()['installedJsonHash']);
        self::assertTrue($cache->installedJsonHashMatches($file));

        unlink($file);
    }

    #[Test]
    public function setInstalledJsonHashSkipsMissingFileAndUnreadableHash(): void
    {
        $cache = Cache::fromJSON([], '');
        $cache->setInstalledJsonHash(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cache-missing-installed.json');
        self::assertSame('', $cache->jsonSerialize()['installedJsonHash']);

        $path = FilesystemFixtures::pathThatExistsButCannotBeReadAsFile();

        SuppressExpectedErrors::during(static function () use ($cache, $path): void {
            $cache->setInstalledJsonHash($path);
        });

        self::assertSame('', $cache->jsonSerialize()['installedJsonHash']);
    }

    #[Test]
    public function fileHashMatchesWhenFileMissingOrHashDiffers(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'cache-hash-');
        self::assertNotFalse($file);
        file_put_contents($file, 'contents');

        $root         = dirname($file) . DIRECTORY_SEPARATOR;
        $relativePath = str_replace($root, '', $file);
        $cache        = Cache::fromJSON([
            'fileHashes' => [$relativePath => 'not-the-real-hash'],
        ], $root);

        self::assertFalse($cache->fileHashMatches($file));

        $missing = $file . '-gone';
        self::assertFalse($cache->fileHashMatches($missing));

        $unknown = tempnam(sys_get_temp_dir(), 'cache-unknown-');
        self::assertNotFalse($unknown);
        file_put_contents($unknown, 'other');
        self::assertFalse($cache->fileHashMatches($unknown));

        unlink($file);
        unlink($unknown);
    }

    #[Test]
    public function getClassAbsoluteFilePathReturnsNullWhenMissing(): void
    {
        $cache = Cache::fromJSON([], '/tmp/');

        self::assertNull($cache->getClassAbsoluteFilePath(self::class));

        $cache->classFilePath(self::class, '');
        self::assertSame([], $cache->jsonSerialize()['classFilePaths']);
        self::assertNull($cache->getClassAbsoluteFilePath(self::class));
    }

    #[Test]
    public function collectedItemsMissingCollectorReturnsNullAndFalse(): void
    {
        $cache = Cache::fromJSON([], '');
        $cache->collectedItems('wyrihaximus/broadcast', self::class, Collector::class, ['item']);

        self::assertNull($cache->getCollectedItems('wyrihaximus/broadcast', self::class, Item::class));
        self::assertFalse($cache->hasCollectedItems('wyrihaximus/broadcast', self::class, Item::class));
        self::assertFalse($cache->hasCollectedItemsForClass('wyrihaximus/broadcast', 'Other\\Class', [new Collector()]));
        self::assertTrue($cache->hasCollectedItemsForClass('wyrihaximus/broadcast', self::class, []));
    }

    #[Test]
    public function relativePathOutsideRootKeepsOriginalPath(): void
    {
        $outside = tempnam(sys_get_temp_dir(), 'cache-outside-');
        self::assertNotFalse($outside);
        file_put_contents($outside, 'outside');

        $root  = '/definitely/not/the/root/';
        $cache = Cache::fromJSON([], $root);
        $hash  = md5_file($outside);
        self::assertNotFalse($hash);
        $cache->fileHash($outside, $hash);
        $cache->classFilePath(self::class, $outside);

        self::assertTrue($cache->fileHashMatches($outside));
        self::assertSame([$outside => $hash], $cache->jsonSerialize()['fileHashes']);

        $serialized = $cache->jsonSerialize();
        self::assertIsArray($serialized['classFilePaths']);
        self::assertSame($outside, $serialized['classFilePaths'][self::class]);
        self::assertSame(
            str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $root . $outside),
            $cache->getClassAbsoluteFilePath(self::class),
        );

        unlink($outside);
    }

    #[Test]
    public function failedReflectionRoundTrip(): void
    {
        $cache = Cache::fromJSON([
            'failedReflections' => [self::class],
        ], '');

        self::assertTrue($cache->hasFailedReflection(self::class));
        self::assertSame([self::class], $cache->failedReflectionClasses());

        $cache->failedReflection(Item::class);

        $failedReflections = new ReflectionProperty($cache, 'failedReflections');
        self::assertSame([self::class => true, Item::class => true], $failedReflections->getValue($cache));
        self::assertTrue($cache->hasFailedReflection(Item::class));
    }
}
