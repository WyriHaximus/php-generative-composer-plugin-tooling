<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling\Helper;

use RuntimeException;

final class TwigFileDoesNotExist extends RuntimeException
{
    private(set) string $twigTemplateFile;

    public static function create(string $twigTemplateFile): self
    {
        $self                   = new self('Twig template file does not exist');
        $self->twigTemplateFile = $twigTemplateFile;

        return $self;
    }
}
