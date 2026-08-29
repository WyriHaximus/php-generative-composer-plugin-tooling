<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling\Helper;

use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\Composer\GenerativePluginTooling\Helper\ErrorExceptionFactory;
use WyriHaximus\TestUtilities\TestCase;

use function error_clear_last;
use function file_get_contents;

final class ErrorExceptionFactoryTest extends TestCase
{
    #[Test]
    public function createWithoutPreviousError(): void
    {
        error_clear_last();

        $exception = ErrorExceptionFactory::create('Something went wrong');

        self::assertSame('Something went wrong', $exception->getMessage());
    }

    #[Test]
    public function createWithPreviousError(): void
    {
        /** @phpstan-ignore ergebnis.noErrorSuppression */
        @file_get_contents($this->getTmpDir() . 'does-not-exist');

        $exception = ErrorExceptionFactory::create('Something went wrong');

        self::assertStringStartsWith('Something went wrong with error: ', $exception->getMessage());
    }
}
