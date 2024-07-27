<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling\Filter\Class;

use Roave\BetterReflection\Reflection\ReflectionClass;
use WyriHaximus\Composer\GenerativePluginTooling\ClassFilter;

final readonly class ImplementsInterface implements ClassFilter
{
    private const DOESNT_IMPLEMENTS_REQUIRED_INTERFACES = false;
    private const IMPLEMENTS_AN_REQUIRED_INTERFACE      = true;
    /** @var array<class-string> */
    private array $interfaces;

    /** @param class-string ...$interfaces */
    public function __construct(string ...$interfaces)
    {
        $this->interfaces = $interfaces;
    }

    public function __invoke(ReflectionClass $reflectionClass): bool
    {
        foreach ($this->interfaces as $interface) {
            if ($reflectionClass->implementsInterface($interface)) {
                return self::IMPLEMENTS_AN_REQUIRED_INTERFACE;
            }
        }

        return self::DOESNT_IMPLEMENTS_REQUIRED_INTERFACES;
    }
}
