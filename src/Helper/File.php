<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling\Helper;

use function chmod;
use function file_put_contents;

final class File
{
    public static function write(
        string $filename,
        string $data,
        int $mode = 0664,
    ): void {
        file_put_contents($filename, $data);
        chmod($filename, $mode);
    }
}
