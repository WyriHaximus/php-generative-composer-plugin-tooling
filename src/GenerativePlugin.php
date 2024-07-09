<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling;

interface GenerativePlugin
{
    public static function name(): string;

    public static function log(LogStages $stage): string;

    /** @return iterable<ClassFilter|PackageFilter> */
    public function filters(): iterable;

    /** @return iterable<ItemCollector> */
    public function collectors(): iterable;

    public function compile(string $rootPath, Item ...$items): void;
}
