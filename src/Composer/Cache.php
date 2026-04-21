<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use WyriHaximus\Composer\GenerativePluginTooling\Cache\CacheFilePath;
use WyriHaximus\Composer\GenerativePluginTooling\Cache\Store;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

final class Cache implements PluginInterface, EventSubscriberInterface
{
    /** @return array<string, string> */
    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::PRE_AUTOLOAD_DUMP => [
                ['loadCache', PHP_INT_MAX],
                ['saveCache', PHP_INT_MIN],
            ],
        ];
    }

    public function activate(Composer $composer, IOInterface $io): void
    {
        // does nothing, see getSubscribedEvents() instead.
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // does nothing, see getSubscribedEvents() instead.
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // does nothing, see getSubscribedEvents() instead.
    }

    /** @phpstan-ignore shipmonk.deadMethod */
    public static function loadCache(Event $event): void
    {
        $cacheLocation = CacheLocator::locate($event->getComposer());
        if (! $cacheLocation instanceof CacheFilePath) {
            return;
        }

        $event->getIO()->write('<info>wyrihaximus/generative-composer-plugin-tooling:</info> Loading Cache');
        Store::setUp($cacheLocation);
        $event->getIO()->write('<info>wyrihaximus/generative-composer-plugin-tooling:</info> Loaded Cache');
    }

    /** @phpstan-ignore shipmonk.deadMethod */
    public static function saveCache(Event $event): void
    {
        $cacheLocation = CacheLocator::locate($event->getComposer());
        if (! $cacheLocation instanceof CacheFilePath) {
            return;
        }

        $event->getIO()->write('<info>wyrihaximus/generative-composer-plugin-tooling:</info> Storing Cache');
        Store::store();
        $event->getIO()->write('<info>wyrihaximus/generative-composer-plugin-tooling:</info> Stored Cache');
    }
}
