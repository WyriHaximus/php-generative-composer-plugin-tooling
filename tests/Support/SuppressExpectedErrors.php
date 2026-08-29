<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling\Support;

use function ini_get;
use function ini_set;
use function is_string;
use function restore_error_handler;
use function set_error_handler;

final class SuppressExpectedErrors
{
    /**
     * @param callable(): T $callback
     *
     * @return T
     *
     * @template T
     */
    public static function during(callable $callback): mixed
    {
        $displayErrors = ini_get('display_errors');
        ini_set('display_errors', '0');
        set_error_handler(static fn (): bool => false);

        try {
            return $callback();
        } finally {
            restore_error_handler();
            ini_set('display_errors', is_string($displayErrors) ? $displayErrors : '0');
        }
    }
}
