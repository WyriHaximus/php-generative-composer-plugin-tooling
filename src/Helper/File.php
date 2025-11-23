<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling\Helper;

use function chmod;
use function dirname;
use function file_exists;
use function file_put_contents;
use function mkdir;

final class File
{
    /** @phpstan-ignore ergebnis.noParameterWithNullDefaultValue,ergebnis.noParameterWithNullableTypeDeclaration */
    public static function write(
        string $filename,
        string $data,
        int|null $mode = null, // Defaults to 0o764 but given is a wrapper around C functions, we need to do shit like this
    ): void {
        $mode          ??= 0o764;
        $parentDirectory = dirname($filename);
        if (! file_exists($parentDirectory)) {
            mkdir($parentDirectory, $mode, true);
        }

        file_put_contents($filename, $data);
        chmod($filename, $mode);
    }
}
