<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling;

use Roave\BetterReflection\Reflector\DefaultReflector;

use function array_key_exists;

final class ClassReflectorStore
{
    private static self|null $instance = null;

    /** @var array<string, DefaultReflector> */
    private array $knownClassReflectors = [];

    private static function instance(): self
    {
        if (! self::$instance instanceof self) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function has(string $vendorDir): bool
    {
        return array_key_exists($vendorDir, self::instance()->knownClassReflectors);
    }

    public static function add(string $vendorDir, DefaultReflector $reflector): void
    {
        self::instance()->knownClassReflectors[$vendorDir] = $reflector;
    }

    public static function get(string $vendorDir): DefaultReflector
    {
        return self::instance()->knownClassReflectors[$vendorDir];
    }

    /** @phpstan-ignore shipmonk.deadMethod */
    public static function reset(): void
    {
        self::instance()->knownClassReflectors = [];
    }
}
