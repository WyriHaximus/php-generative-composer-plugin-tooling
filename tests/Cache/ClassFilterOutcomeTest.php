<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling\Cache;

use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\Composer\GenerativePluginTooling\Cache;
use WyriHaximus\TestUtilities\TestCase;

use function ksort;
use function md5;

final class ClassFilterOutcomeTest extends TestCase
{
    #[Test]
    public function storesAndReadsExactKeys(): void
    {
        $cache = Cache::fromJSON([], '');
        $cache->classFilterOutcome('Vendor\\Class', 'filter-hash', true);

        self::assertSame(
            [md5('Vendor\\Class_=_filter-hash') => true],
            $cache->jsonSerialize()['classFilterOutcome'],
        );
        self::assertTrue($cache->getClassFilterOutcome('Vendor\\Class', 'filter-hash'));
        self::assertNull($cache->getClassFilterOutcome('Vendor\\Class', 'other'));
        self::assertNull($cache->getClassFilterOutcome('Other\\Class', 'filter-hash'));
    }

    #[Test]
    public function storesMultipleHashesForSameClass(): void
    {
        $cache = Cache::fromJSON([], '');
        $cache->classFilterOutcome('A\\B', 'hash-one', true);
        $cache->classFilterOutcome('A\\B', 'hash-two', false);

        self::assertSame([
            md5('A\\B_=_hash-one') => true,
            md5('A\\B_=_hash-two') => false,
        ], $cache->jsonSerialize()['classFilterOutcome']);
        self::assertTrue($cache->getClassFilterOutcome('A\\B', 'hash-one'));
        self::assertFalse($cache->getClassFilterOutcome('A\\B', 'hash-two'));
    }

    #[Test]
    public function keyDistinguishesClassesWithSameFilterHash(): void
    {
        $cache = Cache::fromJSON([], '');
        $cache->classFilterOutcome('ClassA', 'shared-hash', true);
        $cache->classFilterOutcome('ClassB', 'shared-hash', false);

        $serialized = $cache->jsonSerialize();
        self::assertIsArray($serialized['classFilterOutcome']);
        $outcomes = $serialized['classFilterOutcome'];
        $expected = [
            md5('ClassA_=_shared-hash') => true,
            md5('ClassB_=_shared-hash') => false,
        ];
        ksort($expected);
        ksort($outcomes);
        self::assertSame($expected, $outcomes);
        self::assertTrue($cache->getClassFilterOutcome('ClassA', 'shared-hash'));
        self::assertFalse($cache->getClassFilterOutcome('ClassB', 'shared-hash'));
    }

    #[Test]
    public function fromJSONPreseededOutcomesRoundTrip(): void
    {
        $preseededKey = md5('Preseeded\\Class_=_preseeded-hash');
        $cache        = Cache::fromJSON([
            'classFilterOutcome' => [$preseededKey => false],
        ], '');

        self::assertFalse($cache->getClassFilterOutcome('Preseeded\\Class', 'preseeded-hash'));
        self::assertNull($cache->getClassFilterOutcome('Preseeded\\Class', 'missing-hash'));
    }

    #[Test]
    public function disabledCacheNeitherStoresNorReads(): void
    {
        $key   = md5('Vendor\\Class_=_filter-hash');
        $cache = new Cache('', [], [$key => true], [], [], [], '', false, []);

        self::assertNull($cache->getClassFilterOutcome('Vendor\\Class', 'filter-hash'));

        $cache->classFilterOutcome('Vendor\\Class', 'filter-hash', false);
        self::assertSame([$key => true], $cache->jsonSerialize()['classFilterOutcome']);
        self::assertNull($cache->getClassFilterOutcome('Vendor\\Class', 'filter-hash'));
    }
}
