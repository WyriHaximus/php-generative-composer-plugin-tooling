<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling\Filter\Class;

use PHPUnit\Framework\Attributes\Test;
use Roave\BetterReflection\Reflection\ReflectionClass;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Class\HasAttributes;
use WyriHaximus\Tests\Composer\GenerativePluginTooling\Plugin;
use WyriHaximus\Tests\Composer\GenerativePluginTooling\SomePluginAttribute;
use WyriHaximus\TestUtilities\TestCase;

final class HasAttributesTest extends TestCase
{
    #[Test]
    public function hasAttribute(): void
    {
        self::assertTrue(
            new HasAttributes(SomePluginAttribute::class)->__invoke(ReflectionClass::createFromName(Plugin::class)),
        );
    }
}
