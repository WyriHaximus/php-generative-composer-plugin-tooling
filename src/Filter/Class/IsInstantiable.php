<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling\Filter\Class;

use Roave\BetterReflection\Reflection\ReflectionClass;
use WyriHaximus\Composer\GenerativePluginTooling\ClassFilter;

final readonly class IsInstantiable implements ClassFilter
{
    public function __invoke(ReflectionClass $reflectionClass): bool
    {
        return $reflectionClass->isInstantiable();
    }
}
