<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling;

use Roave\BetterReflection\Reflection\ReflectionClass;

interface ClassFilter
{
    public function __invoke(ReflectionClass $reflectionClass): bool;
}
