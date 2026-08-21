<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling\Filter\Operators\Package;

use Composer\Package\RootPackage;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Operators\Package\LogicalNot;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Package\ComposerJsonHasItemWithSpecificValue;
use WyriHaximus\TestUtilities\TestCase;

final class LogicalNotTest extends TestCase
{
    #[Test]
    public function invokeNegatesInnerFilter(): void
    {
        $package = new RootPackage('wyrihaximus/makefiles', 'dev-master', 'dev-master');
        $package->setExtra([
            'wyrihaximus' => [
                'broadcast' => ['has-listeners' => true],
            ],
        ]);

        $filter = new LogicalNot(
            new ComposerJsonHasItemWithSpecificValue('wyrihaximus.broadcast.has-listeners', true),
        );

        self::assertFalse($filter($package));

        $package->setExtra([
            'wyrihaximus' => [
                'broadcast' => ['has-listeners' => false],
            ],
        ]);

        self::assertTrue($filter($package));
    }
}
