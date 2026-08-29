<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling;

use WyriHaximus\Composer\GenerativePluginTooling\ClassFilter;
use WyriHaximus\Composer\GenerativePluginTooling\GenerativePlugin;
use WyriHaximus\Composer\GenerativePluginTooling\Item as ItemContract;
use WyriHaximus\Composer\GenerativePluginTooling\ItemCollector;
use WyriHaximus\Composer\GenerativePluginTooling\LogStages;
use WyriHaximus\Composer\GenerativePluginTooling\PackageFilter;

final class ConfigurablePlugin implements GenerativePlugin
{
    /** @var array<ItemContract> */
    private array $items = [];

    /**
     * @param iterable<ClassFilter|PackageFilter> $filters
     * @param iterable<ItemCollector>             $collectors
     */
    public function __construct(
        private readonly iterable $filters,
        private readonly iterable $collectors,
    ) {
    }

    public static function name(): string
    {
        return 'wyrihaximus/makefiles';
    }

    public static function log(LogStages $stage): string
    {
        return Plugin::log($stage);
    }

    /** @inheritDoc */
    public function filters(): iterable
    {
        yield from $this->filters;
    }

    /** @inheritDoc */
    public function collectors(): iterable
    {
        yield from $this->collectors;
    }

    public function compile(string $rootPath, ItemContract ...$items): void
    {
        $this->items = $items;
    }

    /** @return array<ItemContract> */
    public function compiledItems(): array
    {
        return $this->items;
    }
}
