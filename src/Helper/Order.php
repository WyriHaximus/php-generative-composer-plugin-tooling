<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling\Helper;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

final class Order
{
    public const int EVERYONE_ALSO_MUST_TO_GO_AFTER_ME = PHP_INT_MAX;
    public const int FIRST                             = self::EVERYONE_ALSO_MUST_TO_GO_AFTER_ME - 1024;
    public const int EARLY                             = self::EVERYONE_ALSO_MUST_TO_GO_AFTER_ME - 4096;

    public const int MIDDLE                             = 0;
    public const int LATE                               = self::EVERYONE_ALSO_MUST_TO_GO_BEFORE_ME + 4096;
    public const int LAST                               = self::EVERYONE_ALSO_MUST_TO_GO_BEFORE_ME + 1024;
    public const int EVERYONE_ALSO_MUST_TO_GO_BEFORE_ME = PHP_INT_MIN;
}
