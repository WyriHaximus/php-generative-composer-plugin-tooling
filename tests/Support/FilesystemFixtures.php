<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling\Support;

use PHPUnit\Framework\Assert;

use function chdir;
use function chmod;
use function closedir;
use function dirname;
use function function_exists;
use function getcwd;
use function is_dir;
use function is_file;
use function mkdir;
use function opendir;
use function posix_mkfifo;
use function rmdir;
use function stream_wrapper_register;
use function unlink;

use const DIRECTORY_SEPARATOR;

final class FilesystemFixtures
{
    private const string UNREADABLE_SCHEME = 'wyrihaximus-unreadable';

    private static bool $unreadableRegistered = false;

    /** Path where reads/hashes fail via a stream wrapper (not a real file). */
    public static function pathThatExistsButCannotBeReadAsFile(): string
    {
        if (! self::$unreadableRegistered) {
            stream_wrapper_register(self::UNREADABLE_SCHEME, UnreadableStreamWrapper::class);
            self::$unreadableRegistered = true;
        }

        return self::UNREADABLE_SCHEME . '://installed.json';
    }

    /** Directory at `$path`: exists, not a file (`is_file` false). */
    public static function createUnreadableFilesystemPath(string $path): void
    {
        self::ensureParentDir($path);

        if (is_file($path)) {
            unlink($path);
        }

        if (is_dir($path)) {
            return;
        }

        mkdir($path, 0o755, true);
    }

    /** @param callable(): void $callback */
    public static function withBlockedRmdir(string $parent, string $child, callable $callback): void
    {
        $release = self::acquireRmdirBlock($parent, $child);
        if ($release === null) {
            Assert::markTestSkipped('Unable to block rmdir on this platform');
        }

        try {
            $callback();
        } finally {
            $release();
        }
    }

    private static function ensureParentDir(string $path): void
    {
        $parent = dirname($path);
        if (is_dir($parent)) {
            return;
        }

        mkdir($parent, 0o755, true);
    }

    /** @return (callable(): void)|null */
    private static function acquireRmdirBlock(string $parent, string $child): callable|null
    {
        if (function_exists('posix_mkfifo')) {
            $fifo = $child . DIRECTORY_SEPARATOR . 'block';
            /** @phpstan-ignore ergebnis.noErrorSuppression */
            if (@posix_mkfifo($fifo, 0o644)) {
                /** @phpstan-ignore ergebnis.noErrorSuppression */
                if (! @rmdir($child)) {
                    return static function () use ($fifo): void {
                        /** @phpstan-ignore ergebnis.noErrorSuppression */
                        @unlink($fifo);
                    };
                }

                /** @phpstan-ignore ergebnis.noErrorSuppression */
                @unlink($fifo);
            }
        }

        $previousCwd = getcwd();
        if (chdir($child)) {
            /** @phpstan-ignore ergebnis.noErrorSuppression */
            if (! @rmdir($child)) {
                return static function () use ($previousCwd): void {
                    chdir($previousCwd);
                };
            }

            chdir($previousCwd);
        }

        $handle = opendir($child);
        if ($handle !== false) {
            /** @phpstan-ignore ergebnis.noErrorSuppression */
            if (! @rmdir($child)) {
                return static function () use ($handle): void {
                    closedir($handle);
                };
            }

            closedir($handle);
            mkdir($child);
        }

        chmod($parent, 0o555);
        /** @phpstan-ignore ergebnis.noErrorSuppression */
        if (! @rmdir($child)) {
            return static function () use ($parent): void {
                chmod($parent, 0o755);
            };
        }

        chmod($parent, 0o755);

        return null;
    }
}
