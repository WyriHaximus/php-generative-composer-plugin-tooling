<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling;

use WyriHaximus\Composer\GenerativePluginTooling\GenerativePluginExecutioner;
use WyriHaximus\TestUtilities\TestCase;

final class CreationTest extends TestCase
{
    /** @test */
    public function aboveZero(): void
    {
        self::assertGreaterThan(0, GenerativePluginExecutioner::timeOfCreation());
    }
}
