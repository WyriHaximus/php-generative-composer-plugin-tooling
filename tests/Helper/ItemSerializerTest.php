<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling\Helper;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WyriHaximus\Composer\GenerativePluginTooling\Helper\ItemSerializer;
use WyriHaximus\Tests\Composer\GenerativePluginTooling\Item;
use WyriHaximus\TestUtilities\TestCase;

use function base64_encode;
use function serialize;

final class ItemSerializerTest extends TestCase
{
    #[Test]
    public function roundTrip(): void
    {
        $item = new Item('event', self::class, 'method', false, true);

        self::assertEquals($item, ItemSerializer::unserialize(ItemSerializer::serialize($item)));
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidPayloads(): iterable
    {
        yield 'invalid base64' => ['%%%not-base64%%%', 'Cached item payload is not valid base64.'];
        yield 'not an item' => [base64_encode(serialize('string')), 'Cached item payload did not unserialize to an Item.'];
        yield 'array' => [base64_encode(serialize([])), 'Cached item payload did not unserialize to an Item.'];
    }

    #[Test]
    #[DataProvider('invalidPayloads')]
    public function unserializeRejectsInvalidPayloads(string $payload, string $message): void
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessageIsOrContains($message);

        ItemSerializer::unserialize($payload);
    }
}
