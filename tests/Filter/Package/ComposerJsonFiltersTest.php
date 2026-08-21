<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling\Filter\Package;

use Composer\Package\Link;
use Composer\Package\RootPackage;
use Composer\Semver\Constraint\Constraint;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Package\ComposerJsonHasItemWithSpecificValue;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Package\ComposerJsonRequiresSpecificPackage;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Package\PackageType;
use WyriHaximus\TestUtilities\TestCase;

final class ComposerJsonFiltersTest extends TestCase
{
    #[Test]
    public function hasItemWithSpecificValue(): void
    {
        $package = new RootPackage('wyrihaximus/makefiles', 'dev-master', 'dev-master');
        $package->setExtra([
            'wyrihaximus' => [
                'broadcast' => ['has-listeners' => true],
            ],
        ]);

        $matching = new ComposerJsonHasItemWithSpecificValue('wyrihaximus.broadcast.has-listeners', true);
        $mismatch = new ComposerJsonHasItemWithSpecificValue('wyrihaximus.broadcast.has-listeners', false);
        $missing  = new ComposerJsonHasItemWithSpecificValue('wyrihaximus.broadcast.missing', true);
        $notArray = new ComposerJsonHasItemWithSpecificValue('wyrihaximus.broadcast.has-listeners.nested', true);

        self::assertTrue($matching($package));
        self::assertFalse($mismatch($package));
        self::assertFalse($missing($package));
        self::assertFalse($notArray($package));
    }

    #[Test]
    public function requiresSpecificPackage(): void
    {
        $package = new RootPackage('wyrihaximus/makefiles', 'dev-master', 'dev-master');
        $package->setRequires([
            'wyrihaximus/simple-twig' => new Link(
                'wyrihaximus/makefiles',
                'wyrihaximus/simple-twig',
                new Constraint('=', '2.0.0'),
            ),
        ]);
        $package->setDevRequires([
            'phpunit/phpunit' => new Link(
                'wyrihaximus/makefiles',
                'phpunit/phpunit',
                new Constraint('=', '10.0.0'),
            ),
        ]);

        $productionFound    = new ComposerJsonRequiresSpecificPackage('wyrihaximus/simple-twig', PackageType::PRODUCTION);
        $productionMissing  = new ComposerJsonRequiresSpecificPackage('phpunit/phpunit', PackageType::PRODUCTION);
        $developmentFound   = new ComposerJsonRequiresSpecificPackage('phpunit/phpunit', PackageType::DEVELOPMENT);
        $developmentMissing = new ComposerJsonRequiresSpecificPackage('wyrihaximus/simple-twig', PackageType::DEVELOPMENT);

        self::assertTrue($productionFound($package));
        self::assertFalse($productionMissing($package));
        self::assertTrue($developmentFound($package));
        self::assertFalse($developmentMissing($package));
    }
}
