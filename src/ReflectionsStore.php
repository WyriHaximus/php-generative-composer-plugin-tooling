<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling;

use Roave\BetterReflection\Reflection\ReflectionClass;

use function array_key_exists;

final class ReflectionsStore
{
    private static self|null $instance = null;

    /** @var array<class-string, ReflectionClass> */
    private array $knownClassReflections = [];

    private static function instance(): self
    {
        if (! self::$instance instanceof self) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /** @param class-string $class */
    public static function has(string $class): bool
    {
        return array_key_exists($class, self::instance()->knownClassReflections);
    }

    /** @param class-string $class */
    public static function add(string $class, ReflectionClass $reflection): void
    {
        self::instance()->knownClassReflections[$class] = $reflection;
    }

    /** @param class-string $class */
    public static function get(string $class): ReflectionClass
    {
        return self::instance()->knownClassReflections[$class];
    }

    /** @phpstan-ignore shipmonk.deadMethod */
    public static function reset(): void
    {
        self::instance()->knownClassReflections = [];
    }
}
