<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling\Helper;

use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\Composer\GenerativePluginTooling\Helper\Remove;
use WyriHaximus\TestUtilities\TestCase;

use function md5_file;
use function mkdir;
use function touch;

use const DIRECTORY_SEPARATOR;

final class RemoveTest extends TestCase
{
    #[Test]
    public function directoryContents(): void
    {
        $dirName = $this->getTmpDir() . md5_file(__FILE__);

        mkdir($dirName);
        self::assertDirectoryExists($dirName);

        touch($dirName . DIRECTORY_SEPARATOR . 'file');
        self::assertFileExists($dirName . DIRECTORY_SEPARATOR . 'file');

        mkdir($dirName . DIRECTORY_SEPARATOR . 'dir');
        self::assertDirectoryExists($dirName . DIRECTORY_SEPARATOR . 'dir');

        touch($dirName . DIRECTORY_SEPARATOR . 'dir' . DIRECTORY_SEPARATOR . 'file');
        self::assertFileExists($dirName . DIRECTORY_SEPARATOR . 'dir' . DIRECTORY_SEPARATOR . 'file');

        Remove::directoryContents($dirName);

        self::assertFileDoesNotExist($dirName . DIRECTORY_SEPARATOR . 'dir' . DIRECTORY_SEPARATOR . 'file');
        self::assertDirectoryDoesNotExist($dirName . DIRECTORY_SEPARATOR . 'dir');
        self::assertFileDoesNotExist($dirName . DIRECTORY_SEPARATOR . 'file');
        self::assertDirectoryExists($dirName);
    }

    #[Test]
    public function file(): void
    {
        $fileName = $this->getTmpDir() . md5_file(__FILE__) . '.md5';
        touch($fileName);
        self::assertFileExists($fileName);
        Remove::file($fileName);
        self::assertFileDoesNotExist($fileName);
    }
}
