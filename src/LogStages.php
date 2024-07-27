<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling;

enum LogStages
{
    case Error;
    case Init;
    case Collected;
    case Completion;
}
