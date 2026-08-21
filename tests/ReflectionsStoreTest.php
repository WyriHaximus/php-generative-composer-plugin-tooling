<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling;

use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\Test;
use Roave\BetterReflection\Reflection\ReflectionClass;
use WyriHaximus\Composer\GenerativePluginTooling\ReflectionsStore;
use WyriHaximus\TestUtilities\TestCase;

final class ReflectionsStoreTest extends TestCase
{
    #[Before]
    public function resetStore(): void
    {
        ReflectionsStore::reset();
    }

    #[Test]
    public function getReturnsAddedReflection(): void
    {
        $reflection = ReflectionClass::createFromName(Plugin::class);

        ReflectionsStore::add(Plugin::class, $reflection);

        self::assertTrue(ReflectionsStore::has(Plugin::class));
        self::assertSame($reflection, ReflectionsStore::get(Plugin::class));
    }
}
