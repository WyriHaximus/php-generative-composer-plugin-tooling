<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling\Cache;

use Stringable;

final readonly class CacheFilePath implements Stringable
{
    public function __construct(
        public string $root,
        public string $cacheLocation,
    ) {
    }

    public function __toString(): string
    {
        return $this->root . $this->cacheLocation;
    }
}
