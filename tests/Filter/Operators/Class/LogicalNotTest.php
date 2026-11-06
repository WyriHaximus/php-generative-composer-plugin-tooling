<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling\Filter\Operators\Class;

use PHPUnit\Framework\Attributes\Test;
use Roave\BetterReflection\Reflection\ReflectionClass;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Class\ImplementsInterface;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Operators\Class\LogicalNot;
use WyriHaximus\Tests\Composer\GenerativePluginTooling\Filter\Class\Fixtures\Contracts\Worker;
use WyriHaximus\Tests\Composer\GenerativePluginTooling\Filter\Class\Fixtures\WorkerOnly;
use WyriHaximus\TestUtilities\TestCase;

final class LogicalNotTest extends TestCase
{
    #[Test]
    public function invokeNegatesInnerFilter(): void
    {
        $reflectionClass = ReflectionClass::createFromName(WorkerOnly::class);

        $filter = new LogicalNot(new ImplementsInterface(Worker::class));

        self::assertFalse($filter($reflectionClass));

        $notFilter = new LogicalNot($filter);
        self::assertTrue($notFilter($reflectionClass));
    }
}
