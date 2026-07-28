<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling;

use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\Composer\GenerativePluginTooling\Cache;
use WyriHaximus\Composer\GenerativePluginTooling\Helper\ItemSerializer;
use WyriHaximus\TestUtilities\TestCase;

use function array_keys;
use function dirname;
use function file_put_contents;
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
        self::assertFalse($cache->hasFailedReflection(self::class, '', ''));

        $cache->fileHash(__FILE__, 'hash');
        $cache->classFilterOutcome(self::class, self::class, true);
        $cache->failedReflection(self::class, 'hash', 'filters');
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
    public function classFilterOutcomeRoundTrips(): void
    {
        $cache = Cache::fromJSON([], '');

        $cache->classFilterOutcome('WyriHaximus\\Foo', self::class, true);

        self::assertTrue($cache->getClassFilterOutcome('WyriHaximus\\Foo', self::class));
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
    public function jsonSerializeSortsAllKeysRecursively(): void
    {
        $cache = Cache::fromJSON([], '');

        $cache->fileHash('z.php', 'hash-z');
        $cache->fileHash('a.php', 'hash-a');
        $cache->classFilterOutcome(Item::class, 'filter-z', true);
        $cache->classFilterOutcome(self::class, 'filter-a', false);
        $cache->classFilePath(Item::class, 'z.php');
        $cache->classFilePath(self::class, 'a.php');
        $cache->failedReflection(Item::class, 'hash-z', 'filter-hash');
        $cache->failedReflection(self::class, 'hash-a', 'filter-hash');
        $cache->collectedItems('wyrihaximus/z', Item::class, Collector::class, []);
        $cache->collectedItems('wyrihaximus/a', self::class, Collector::class, []);

        $serialized = $cache->jsonSerialize();
        self::assertIsArray($serialized['fileHashes']);
        self::assertIsArray($serialized['classFilePaths']);
        self::assertIsArray($serialized['failedReflections']);
        self::assertIsArray($serialized['collectedItems']);
        /** @var array<string, array<string, array<string, array<int, string>>>> $collectedItems */
        $collectedItems = $serialized['collectedItems'];

        self::assertSame(['classFilePaths', 'classFilterOutcome', 'collectedItems', 'failedReflections', 'fileHashes', 'installedJsonHash'], array_keys($serialized));
        self::assertSame(['a.php', 'z.php'], array_keys($serialized['fileHashes']));
        self::assertSame([self::class, Item::class], array_keys($serialized['classFilePaths']));
        self::assertSame([
            md5(Item::class . '_=_hash-z_=_filter-hash'),
            md5(self::class . '_=_hash-a_=_filter-hash'),
        ], array_keys($serialized['failedReflections']));
        self::assertSame(['wyrihaximus/a', 'wyrihaximus/z'], array_keys($collectedItems));
        self::assertSame([self::class], array_keys($collectedItems['wyrihaximus/a']));
        self::assertSame([Item::class], array_keys($collectedItems['wyrihaximus/z']));
    }

    #[Test]
    public function failedReflectionIsCoupledToClassAndFilterFileHashes(): void
    {
        $cache = Cache::fromJSON([], '');

        $cache->failedReflection(self::class, 'class-hash', 'filter-hash');

        self::assertTrue($cache->hasFailedReflection(self::class, 'class-hash', 'filter-hash'));
        self::assertFalse($cache->hasFailedReflection(self::class, 'other-class-hash', 'filter-hash'));
        self::assertFalse($cache->hasFailedReflection(self::class, 'class-hash', 'other-filter-hash'));
    }

    #[Test]
    public function legacyFailedReflectionListEntriesAreDiscarded(): void
    {
        $cache = Cache::fromJSON([
            'failedReflections' => [self::class],
        ], '');

        self::assertFalse($cache->hasFailedReflection(self::class, '', ''));
    }

    #[Test]
    public function bustOnInstalledJsonChangeClearsCollectedItemsOnly(): void
    {
        $cache = Cache::fromJSON([
            'installedJsonHash' => 'old-hash',
            'fileHashes' => ['src/Foo.php' => 'hash'],
            'classFilterOutcome' => [md5('WyriHaximus\\Foo_=_filter') => true],
            'classFilePaths' => ['WyriHaximus\\Foo' => 'src/Foo.php'],
            'failedReflections' => [md5('WyriHaximus\\Bar_=_class_=_filters') => true],
            'collectedItems' => [
                'wyrihaximus/broadcast' => [
                    'WyriHaximus\\Foo' => [
                        Collector::class => [],
                    ],
                ],
            ],
        ], '');

        $cache->bustOnInstalledJsonChange();

        self::assertSame(['src/Foo.php' => 'hash'], $cache->jsonSerialize()['fileHashes']);
        self::assertSame([md5('WyriHaximus\\Foo_=_filter') => true], $cache->jsonSerialize()['classFilterOutcome']);
        self::assertSame(['WyriHaximus\\Foo' => 'src/Foo.php'], $cache->jsonSerialize()['classFilePaths']);
        self::assertSame([md5('WyriHaximus\\Bar_=_class_=_filters') => true], $cache->jsonSerialize()['failedReflections']);
        self::assertSame([], $cache->jsonSerialize()['collectedItems']);
    }
}
