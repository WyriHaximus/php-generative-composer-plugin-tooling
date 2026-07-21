<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling;

use Composer\IO\NullIO;
use Symfony\Component\Console\Output\StreamOutput;

use function fopen;
use function fseek;
use function stream_get_contents;

final class TestIo extends NullIO
{
    private readonly StreamOutput $output;

    public function __construct()
    {
        /** @phpstan-ignore argument.type */
        $this->output = new StreamOutput(fopen('php://memory', 'rw'), decorated: false);
    }

    public function output(): string
    {
        fseek($this->output->getStream(), 0);

        return stream_get_contents($this->output->getStream());
    }

    /** @inheritDoc */
    public function write($messages, bool $newline = true, int $verbosity = self::NORMAL): void
    {
        $this->output->write($messages, $newline, $verbosity & StreamOutput::OUTPUT_RAW);
    }
}
