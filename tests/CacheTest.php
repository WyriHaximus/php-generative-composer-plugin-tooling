<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling;

use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\Composer\GenerativePluginTooling\Cache;
use WyriHaximus\Composer\GenerativePluginTooling\Helper\ItemSerializer;
use WyriHaximus\TestUtilities\TestCase;

use function dirname;
use function file_put_contents;
use function md5_file;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

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

        $cache->fileHash(__FILE__, 'hash');
        $cache->classFilterOutcome(self::class, self::class, true);
        $cache->failedReflection(self::class);
        $cache->collectedItems('plugin/name', self::class, self::class, [ItemSerializer::serialize(new Item('event', self::class, 'method', false, false))]);

        self::assertSame([
            'installedJsonHash' => '',
            'fileHashes' => [],
            'classFilterOutcome' => [],
            'failedReflections' => [],
            'collectedItems' => [],
        ], $cache->jsonSerialize());
    }

    #[Test]
    public function fileHashMatchesUsesRelativePaths(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'cache-test-');
        self::assertNotFalse($file);
        file_put_contents($file, 'contents');

        $root  = dirname($file) . '/';
        $cache = Cache::fromJSON([], $root);
        $hash  = md5_file($file);
        self::assertNotFalse($hash);
        $cache->fileHash($file, $hash);

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
}
