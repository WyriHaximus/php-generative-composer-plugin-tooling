<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling;

use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflection\ReflectionIntersectionType;
use Roave\BetterReflection\Reflection\ReflectionUnionType;
use WyriHaximus\Composer\GenerativePluginTooling\Item as ItemContract;
use WyriHaximus\Composer\GenerativePluginTooling\ItemCollector;

use function str_starts_with;

final class Collector implements ItemCollector
{
    /** @return iterable<ItemContract> */
    public function collect(ReflectionClass $class): iterable
    {
        foreach ($class->getMethods() as $method) {
            if (! $method->isPublic()) {
                continue;
            }

            if (str_starts_with($method->getName(), '__')) {
                continue;
            }

            if ($method->getNumberOfParameters() !== 1) {
                continue;
            }

            $eventTypeHolder = $method->getParameters()[0]->getType();
            if ($eventTypeHolder instanceof ReflectionIntersectionType) {
                continue;
            }

            if ($eventTypeHolder instanceof ReflectionUnionType) {
                $eventTypes = $eventTypeHolder->getTypes();
            } else {
                $eventTypes = [$eventTypeHolder];
            }

            foreach ($eventTypes as $eventType) {
                yield new Item(
                    (string) $eventType,
                    $class->getName(),
                    $method->getName(),
                    $method->isStatic(),
                    false,
                );
            }
        }
    }
}
