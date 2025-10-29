<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling;

use WyriHaximus\Broadcast\Contracts\Listener;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Class\ImplementsInterface;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Class\IsInstantiable;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Package\ComposerJsonHasItemWithSpecificValue;
use WyriHaximus\Composer\GenerativePluginTooling\GenerativePlugin;
use WyriHaximus\Composer\GenerativePluginTooling\Item as ItemContract;
use WyriHaximus\Composer\GenerativePluginTooling\LogStages;

#[SomePluginAttribute]
final class Plugin implements GenerativePlugin
{
    /** @var array<ItemContract> */
    private array $items = [];

    public static function name(): string
    {
        return 'wyrihaximus/makefiles';
    }

    public static function log(LogStages $stage): string
    {
        return match ($stage) {
            LogStages::Init => 'Locating listeners',
            LogStages::Error => 'An error occurred: %s',
            LogStages::Collected => 'Found %d listener(s)',
            LogStages::Completion => 'Generated static abstract listeners provider in %s second(s)',
        };
    }

    /** @inheritDoc */
    public function filters(): iterable
    {
        yield new ComposerJsonHasItemWithSpecificValue('wyrihaximus.broadcast.has-listeners', true);
        yield new IsInstantiable();
        yield new ImplementsInterface(Listener::class);
    }

    /** @inheritDoc */
    public function collectors(): iterable
    {
        yield new Collector();
    }

    public function compile(string $rootPath, ItemContract ...$items): void
    {
        $this->items = $items;
    }

    /** @return iterable<ItemContract> */
    public function items(): iterable
    {
        yield from $this->items;
    }
}
