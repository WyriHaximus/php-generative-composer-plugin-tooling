<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling\Filter\Operators;

use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Class\IsInstantiable;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Operators\Class\LogicalAnd as ClassLogicalAnd;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Operators\Class\LogicalNot as ClassLogicalNot;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Operators\LogicalAnd;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Operators\LogicalNot;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Operators\Package\LogicalAnd as PackageLogicalAnd;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Operators\Package\LogicalNot as PackageLogicalNot;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Package\ComposerJsonHasItemWithSpecificValue;
use WyriHaximus\TestUtilities\TestCase;

use function iterator_to_array;

final class LogicalOperatorsCreateTest extends TestCase
{
    #[Test]
    public function logicalAndCreateWithOnlyClassFiltersReturnsEarlyWithoutPackageFilter(): void
    {
        $filters = iterator_to_array(LogicalAnd::create(new IsInstantiable()), false);

        self::assertCount(1, $filters);
        self::assertInstanceOf(ClassLogicalAnd::class, $filters[0]);
    }

    #[Test]
    public function logicalAndCreateWithPackageFiltersYieldsPackageLogicalAnd(): void
    {
        $filters = iterator_to_array(
            LogicalAnd::create(new ComposerJsonHasItemWithSpecificValue('wyrihaximus.broadcast.has-listeners', true)),
            false,
        );

        self::assertCount(1, $filters);
        self::assertInstanceOf(PackageLogicalAnd::class, $filters[0]);
    }

    #[Test]
    public function logicalNotCreateWithClassFiltersYieldsClassLogicalNot(): void
    {
        $filters = iterator_to_array(LogicalNot::create(new IsInstantiable()), false);

        self::assertCount(1, $filters);
        self::assertInstanceOf(ClassLogicalNot::class, $filters[0]);
    }

    #[Test]
    public function logicalNotCreateWithPackageFiltersYieldsPackageLogicalNot(): void
    {
        $filters = iterator_to_array(
            LogicalNot::create(new ComposerJsonHasItemWithSpecificValue('wyrihaximus.broadcast.has-listeners', true)),
            false,
        );

        self::assertCount(1, $filters);
        self::assertInstanceOf(PackageLogicalNot::class, $filters[0]);
    }
}
