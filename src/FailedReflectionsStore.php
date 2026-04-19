<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling;

use function array_key_exists;

final class FailedReflectionsStore
{
    private static self|null $instance = null;

    /** @var array<class-string, true> */
    private array $knownFailedReflections = [];

    private static function instance(): self
    {
        if (! self::$instance instanceof self) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /** @param class-string $classname */
    public static function has(string $classname): bool
    {
        return array_key_exists($classname, self::instance()->knownFailedReflections);
    }

    /** @param class-string $classname */
    public static function add(string $classname): void
    {
        self::instance()->knownFailedReflections[$classname] = true;
    }

    /** @phpstan-ignore shipmonk.deadMethod */
    public static function reset(): void
    {
        self::instance()->knownFailedReflections = [];
    }
}
