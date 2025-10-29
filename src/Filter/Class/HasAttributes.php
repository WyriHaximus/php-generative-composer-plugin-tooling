<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling\Filter\Class;

use Roave\BetterReflection\Reflection\ReflectionAttribute;
use Roave\BetterReflection\Reflection\ReflectionClass;
use WyriHaximus\Composer\GenerativePluginTooling\ClassFilter;

use function array_intersect;
use function array_map;
use function count;

final readonly class HasAttributes implements ClassFilter
{
    /** @var array<class-string> */
    private array $attributes;

    /** @param class-string ...$attributes */
    public function __construct(string ...$attributes)
    {
        $this->attributes = $attributes;
    }

    public function __invoke(ReflectionClass $reflectionClass): bool
    {
        return count(
            array_intersect(
                $this->attributes,
                array_map(
                    static fn (ReflectionAttribute $reflectionAttribute): string => $reflectionAttribute->getName(),
                    $reflectionClass->getAttributes(),
                ),
            ),
        ) > 0;
    }
}
