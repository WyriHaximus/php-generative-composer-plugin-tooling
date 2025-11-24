<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling\Helper;

use FilesystemIterator;
use SplFileInfo;

use function is_dir;
use function is_file;
use function rmdir;
use function unlink;

final class Remove
{
    public static function directoryContents(
        string $directory,
    ): void {
        $directoryIterator = new FilesystemIterator($directory);

        foreach ($directoryIterator as $node) {
            if (! $node instanceof SplFileInfo) {
                continue;
            }

            if (is_dir($node->getPathname())) {
                self::directoryContents($node->getPathname());

                /** @phpstan-ignore ergebnis.noErrorSuppression */
                if (! @rmdir($node->getPathname())) {
                    throw ErrorExceptionFactory::create('Error deleting directory: ' . $node->getPathname());
                }

                continue;
            }

            if (! is_file($node->getPathname())) {
                continue;
            }

            self::file($node->getPathname());
        }
    }

    public static function file(
        string $filename,
    ): void {
        /** @phpstan-ignore ergebnis.noErrorSuppression */
        if (! @unlink($filename)) {
            throw ErrorExceptionFactory::create('Error deleting file: ' . $filename);
        }
    }
}
