<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling\Helper;

use RuntimeException;
use WyriHaximus\Composer\GenerativePluginTooling\Item;

use function base64_decode;
use function base64_encode;
use function is_string;
use function serialize;
use function unserialize;

final class ItemSerializer
{
    public static function serialize(Item $item): string
    {
        return base64_encode(serialize($item));
    }

    public static function unserialize(string $encoded): Item
    {
        $serialized = base64_decode($encoded, strict: true);
        if (! is_string($serialized)) {
            throw new RuntimeException('Cached item payload is not valid base64.');
        }

        $item = unserialize($serialized, ['allowed_classes' => true]);
        if (! $item instanceof Item) {
            throw new RuntimeException('Cached item payload did not unserialize to an Item.');
        }

        return $item;
    }
}
