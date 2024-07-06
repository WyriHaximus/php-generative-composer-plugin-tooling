<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling;

use function time;

final class Creation
{
    public static function timeOfCreation(): int
    {
        return time();
    }
}
