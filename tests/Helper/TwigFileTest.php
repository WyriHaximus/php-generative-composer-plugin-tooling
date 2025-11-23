<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling\Helper;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\Composer\GenerativePluginTooling\Helper\TwigFile;
use WyriHaximus\Composer\GenerativePluginTooling\Helper\TwigFileDoesNotExist;
use WyriHaximus\TestUtilities\TestCase;

use function base_convert;
use function file_get_contents;
use function str_replace;

use const DIRECTORY_SEPARATOR;

final class TwigFileTest extends TestCase
{
    /** @param array<int|null> $twifFileRenderExtraArgs */
    #[Test]
    #[DataProvider('twigFileRenderExtraArgsProvider')]
    public function render(array $twifFileRenderExtraArgs): void
    {
        $theBeer      = 'Bourbon Barrel Oro Negro';
        $sourceFile   = __DIR__ . DIRECTORY_SEPARATOR . 'template.twig';
        $renderedFile = $this->getTmpDir() . 'cellar' . DIRECTORY_SEPARATOR . 'dark.bier';
        self::assertFileDoesNotExist($renderedFile);

        TwigFile::render(
            $sourceFile,
            $renderedFile,
            ['beer' => $theBeer],
            ...$twifFileRenderExtraArgs,
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

    /** @return iterable<string, array<mixed>> */
    public static function twigFileRenderExtraArgsProvider(): iterable
    {
        yield 'none' => [
            [],
        ];

        yield 'null' => [
            [null],
        ];

        yield 'default-0o764' => [
            [0o764],
        ];

        for ($i = 0; $i < 8; $i++) {
            for ($j = 0; $j < 8; $j++) {
                yield '0o7' . $i . $j => [
                    [
                        (int) base_convert('0o7' . $i . $j, 8, 10),
                    ],
                ];
            }
        }
    }
}
