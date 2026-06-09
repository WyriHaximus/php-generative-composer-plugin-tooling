<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling\Filter\Package;

/** @api */
enum PackageType: string
{
    case PRODUCTION  = 'prod';
    case DEVELOPMENT = 'dev';
}
