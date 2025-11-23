<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling\Helper;

use WyriHaximus\Twig\SimpleTwig;

use function file_exists;
use function file_get_contents;
use function is_string;

final class TwigFile
{
    /**
     * @param array<string, mixed> $data
     *
     * @phpstan-ignore ergebnis.noParameterWithNullDefaultValue,ergebnis.noParameterWithNullableTypeDeclaration
     */
    public static function render(
        string $templateFile,
        string $renderedFile,
        array $data,
        int|null $mode = null, // Defaults to 0o764 but given is a wrapper around C functions, we need to do shit like this
    ): void {
        $mode ??= 0o764;

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
        File::write(
            $renderedFile,
            $renderedContents,
            $mode,
        );
    }
}
