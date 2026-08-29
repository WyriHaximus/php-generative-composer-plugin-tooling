<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling;

use WyriHaximus\Composer\GenerativePluginTooling\GenerativePlugin;
use WyriHaximus\Composer\GenerativePluginTooling\Item as ItemContract;
use WyriHaximus\Composer\GenerativePluginTooling\LogStages;

final class NameMismatchPlugin implements GenerativePlugin
{
    public static function name(): string
    {
        return 'other/plugin-name';
    }

    public static function log(LogStages $stage): string
    {
        return Plugin::log($stage);
    }

    /** @inheritDoc */
    public function filters(): iterable
    {
        yield from new Plugin()->filters();
    }

    /** @inheritDoc */
    public function collectors(): iterable
    {
        yield from new Plugin()->collectors();
    }

    public function compile(string $rootPath, ItemContract ...$items): void
    {
    }
}
