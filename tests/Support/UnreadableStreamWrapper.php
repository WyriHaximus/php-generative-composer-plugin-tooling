<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling\Support;

use function time;

/**
 * Appears as an existing file via `url_stat`, but refuses `stream_open` so reads/hashes fail.
 *
 * Stream wrapper methods must use PHP's snake_case names.
 */
final class UnreadableStreamWrapper
{
    /**
     * Required by PHP's stream wrapper API.
     *
     * @var resource|null
     * @phpstan-ignore shipmonk.deadProperty.neverRead,shipmonk.deadProperty.neverWritten
     */
    public $context;

    // phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps,Squiz.NamingConventions.ValidVariableName.NotCamelCaps

    /** @phpstan-ignore ergebnis.noParameterPassedByReference,ergebnis.noParameterWithNullableTypeDeclaration */
    public function stream_open(string $path, string $mode, int $options, string|null &$opened_path): bool
    {
        return false;
    }

    /** @return array<string, int> */
    public function url_stat(string $path, int $flags): array
    {
        $now = time();

        return [
            'dev' => 0,
            'ino' => 0,
            'mode' => 0100644,
            'nlink' => 1,
            'uid' => 0,
            'gid' => 0,
            'rdev' => 0,
            'size' => 1,
            'atime' => $now,
            'mtime' => $now,
            'ctime' => $now,
            'blksize' => -1,
            'blocks' => -1,
        ];
    }

    // phpcs:enable
}
