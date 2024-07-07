<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling;

use WyriHaximus\Composer\GenerativePluginTooling\Creation;
use WyriHaximus\TestUtilities\TestCase;

final class CreationTest extends TestCase
{
    /** @test */
    public function aboveZero(): void
    {
        self::assertGreaterThan(0, Creation::timeOfCreation());
    }
}
