<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling\Cache;

use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\Composer\GenerativePluginTooling\Cache\ClassFilterFileContentHash;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Class\ImplementsInterface;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Operators\Class\LogicalAnd;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Operators\Class\LogicalNot;
use WyriHaximus\Tests\Composer\GenerativePluginTooling\Filter\Class\Fixtures\Contracts\Action;
use WyriHaximus\Tests\Composer\GenerativePluginTooling\Filter\Class\Fixtures\Contracts\Worker;
use WyriHaximus\Tests\Composer\GenerativePluginTooling\Helper\Fixtures\ClassFilterWithNonClassFilterProperty;
use WyriHaximus\TestUtilities\TestCase;

final class ClassFilterFileContentHashTest extends TestCase
{
    #[Test]
    public function hashIncludesNestedFilterFiles(): void
    {
        $filters = [
            new LogicalAnd(
                new ImplementsInterface(Worker::class),
                new LogicalNot(new ImplementsInterface(Action::class)),
            ),
        ];

        $hash = ClassFilterFileContentHash::hash($filters);

        self::assertNotSame('', $hash);
        self::assertSame($hash, ClassFilterFileContentHash::hash($filters));
    }

    #[Test]
    public function hashIgnoresNonClassFilterFilterProperty(): void
    {
        $filters = [
            new ClassFilterWithNonClassFilterProperty('not-a-class-filter'),
        ];

        $hash = ClassFilterFileContentHash::hash($filters);

        self::assertNotSame('', $hash);
        self::assertSame($hash, ClassFilterFileContentHash::hash($filters));
    }
}
