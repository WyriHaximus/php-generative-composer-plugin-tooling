<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling\Filter\Operators\Class;

use Roave\BetterReflection\Reflection\ReflectionClass;
use WyriHaximus\Composer\GenerativePluginTooling\ClassFilter;

final readonly class LogicalNot implements ClassFilter
{
    public function __construct(
        private ClassFilter $filter,
    ) {
    }

    public function __invoke(ReflectionClass $class): bool
    {
        return ! ($this->filter)($class);
    }
}
