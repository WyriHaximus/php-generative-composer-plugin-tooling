<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling\Helper;

use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use WyriHaximus\Composer\GenerativePluginTooling\Helper\Order;
use WyriHaximus\TestUtilities\TestCase;

use function usort;

final class OrderTest extends TestCase
{
    #[Test]
    public function order(): void
    {
        /** @var array<int> $priorities */
        $priorities = new ReflectionClass(Order::class)->getConstants();

        usort($priorities, static fn (int $left, int $right): int => $right <=> $left);

        self::assertSame(
            [
                Order::EVERYONE_ALSO_MUST_TO_GO_AFTER_ME,
                Order::FIRST,
                Order::EARLY,
                Order::MIDDLE,
                Order::LATE,
                Order::LAST,
                Order::EVERYONE_ALSO_MUST_TO_GO_BEFORE_ME,
            ],
            $priorities,
        );
    }
}
