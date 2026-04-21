<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling\Cache;

use RuntimeException;
use WyriHaximus\Composer\GenerativePluginTooling\Cache;

use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function json_decode;
use function json_encode;

final class Store
{
    private static self|null $instance = null;

    private readonly Cache $cache;

    private function __construct(private readonly CacheFilePath $cacheFilePath)
    {
        /** @var array|array{fileHashes: array<string, string>} $json */
        $json = [];
        if ((string) $this->cacheFilePath !== '' && file_exists((string) $this->cacheFilePath)) {
            $json = json_decode(file_get_contents((string) $this->cacheFilePath), true) ?? [];
        }

        $this->cache = Cache::fromJSON($json, $this->cacheFilePath->root);
    }

    public static function setUp(CacheFilePath $cacheFilePath): void
    {
        if (self::$instance instanceof self) {
            return;
        }

        self::$instance = new self($cacheFilePath);
    }

    public static function store(): void
    {
        if ((string) self::instance()->cacheFilePath === '') {
            return;
        }

        file_put_contents((string) self::instance()->cacheFilePath, json_encode(self::instance()->cache));
    }

    private static function instance(): self
    {
        if (! self::$instance instanceof self) {
            throw new RuntimeException('Cache Store instance has not been set up.');
        }

        return self::$instance;
    }

    public static function cache(): Cache
    {
        return self::instance()->cache;
    }
}
