<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling\Helper;

use WyriHaximus\Twig\SimpleTwig;

use function chmod;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_string;

final class TwigFile
{
    /** @param array<string, mixed> $data */
    public static function render(
        string $templateFile,
        string $renderedFile,
        array $data,
        int $mode = 0664,
    ): void {
        if (! file_exists($templateFile)) {
            throw TwigFileDoesNotExist::create($templateFile);
        }

        $templateContents = file_get_contents($templateFile);
        if (! is_string($templateContents)) {
            throw TwigFileDoesNotExist::create($templateFile);
        }

        $renderedContents = SimpleTwig::render(
            $templateContents,
            $data,
        );
        file_put_contents($renderedFile, $renderedContents);
        chmod($renderedFile, $mode);
    }
}
