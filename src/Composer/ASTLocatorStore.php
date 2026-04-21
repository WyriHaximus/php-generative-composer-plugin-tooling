<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling\Composer;

use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\SourceLocator\Ast\Locator as AstLocator;

final class ASTLocatorStore
{
    private static self|null $instance = null;

    public function __construct(public readonly AstLocator $astLocator)
    {
    }


    public static function ASTLocator(): AstLocator
    {
        if (! self::$instance instanceof self) {
            self::$instance = new self(new BetterReflection()->astLocator());
        }

        return self::$instance->astLocator;
    }
}
