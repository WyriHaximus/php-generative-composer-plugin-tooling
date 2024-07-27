<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling;

use Roave\BetterReflection\Reflection\ReflectionClass;

interface ItemCollector
{
    /** @return iterable<Item> */
    public function collect(ReflectionClass $class): iterable;
}
