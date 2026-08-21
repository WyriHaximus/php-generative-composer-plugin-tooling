<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling\Helper\Fixtures;

use Roave\BetterReflection\Reflection\ReflectionClass;
use WyriHaximus\Composer\GenerativePluginTooling\ClassFilter;

final readonly class ClassFilterWithNonClassFilterProperty implements ClassFilter
{
    public function __construct(
        private string $filter,
    ) {
    }

    public function __invoke(ReflectionClass $class): bool
    {
        return $this->filter !== '';
    }
}
