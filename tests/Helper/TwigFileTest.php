<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling\Helper;

use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\Composer\GenerativePluginTooling\Helper\TwigFile;
use WyriHaximus\Composer\GenerativePluginTooling\Helper\TwigFileDoesNotExist;
use WyriHaximus\TestUtilities\TestCase;

use function file_get_contents;
use function str_replace;

use const DIRECTORY_SEPARATOR;

final class TwigFileTest extends TestCase
{
    #[Test]
    public function render(): void
    {
        $theBeer      = 'Bourbon Barrel Oro Negro';
        $sourceFile   = __DIR__ . DIRECTORY_SEPARATOR . 'template.twig';
        $renderedFile = $this->getTmpDir() . 'dark.bier';
        self::assertFileDoesNotExist($renderedFile);

        TwigFile::render(
            $sourceFile,
            $renderedFile,
            ['beer' => $theBeer],
        );

        self::assertFileExists($renderedFile);
        self::assertSame(
            str_replace(
                '{{ beer }}',
                $theBeer,
                file_get_contents($sourceFile), /** @phpstan-ignore-line */
            ),
            file_get_contents($renderedFile),
        );
    }

    #[Test]
    public function twigTemplateFileDoesNotExist(): void
    {
        $templateFile = 'does-not-exist.twig';
        $this->expectExceptionObject(TwigFileDoesNotExist::create($templateFile));

        try {
            TwigFile::render(
                $templateFile,
                $this->getTmpDir() . 'dark.bier',
                [],
            );
        } catch (TwigFileDoesNotExist $reThrowMe) {
            self::assertSame($templateFile, $reThrowMe->twigTemplateFile);

            throw $reThrowMe;
        }
    }
}
