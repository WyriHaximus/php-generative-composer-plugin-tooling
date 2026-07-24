<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling\Filter\Class;

use PHPUnit\Framework\Attributes\Test;
use Roave\BetterReflection\Reflection\ReflectionClass;
use WyriHaximus\Broadcast\Contracts\Listener;
use WyriHaximus\Broadcast\Dummy\AsyncListener;
use WyriHaximus\Composer\GenerativePluginTooling\Cache;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Class\ImplementsInterface;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Operators\Class\LogicalAnd;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Operators\Class\LogicalNot;
use WyriHaximus\Tests\Composer\GenerativePluginTooling\Filter\Class\Fixtures\Contracts\Action;
use WyriHaximus\Tests\Composer\GenerativePluginTooling\Filter\Class\Fixtures\Contracts\Worker;
use WyriHaximus\Tests\Composer\GenerativePluginTooling\Filter\Class\Fixtures\WorkerOnly;
use WyriHaximus\TestUtilities\TestCase;

use function md5;
use function serialize;

final class ClassFilterSerializationTest extends TestCase
{
    #[Test]
    public function serializedImplementsInterfaceFiltersCacheSeparately(): void
    {
        $reflectionClass = ReflectionClass::createFromName(AsyncListener::class);
        $className       = AsyncListener::class;

        $listenerFilter = new ImplementsInterface(Listener::class);
        $actionFilter   = new ImplementsInterface(Action::class);

        self::assertTrue($listenerFilter($reflectionClass));
        self::assertFalse($actionFilter($reflectionClass));
        self::assertNotSame(serialize($listenerFilter), serialize($actionFilter));

        $cache = Cache::fromJSON([], '');
        $cache->classFilterOutcome($className, md5(serialize($listenerFilter)), true);
        $cache->classFilterOutcome($className, md5(serialize($actionFilter)), false);

        self::assertTrue($cache->getClassFilterOutcome($className, md5(serialize($listenerFilter))));
        self::assertFalse($cache->getClassFilterOutcome($className, md5(serialize($actionFilter))));
    }

    #[Test]
    public function serializedOperatorFiltersIncludeNestedState(): void
    {
        $workerFilter = new ImplementsInterface(Worker::class);
        $actionFilter = new ImplementsInterface(Action::class);
        $andFilter    = new LogicalAnd($workerFilter, new LogicalNot($actionFilter));

        $reflectionClass = ReflectionClass::createFromName(WorkerOnly::class);
        self::assertTrue($andFilter($reflectionClass));
        self::assertSame(serialize($andFilter), serialize(new LogicalAnd($workerFilter, new LogicalNot($actionFilter))));
    }
}
