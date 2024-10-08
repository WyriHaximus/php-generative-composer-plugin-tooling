<?php

declare(strict_types=1);

namespace WyriHaximus\Tests\Composer\GenerativePluginTooling;

use Composer\Composer;
use Composer\Config;
use Composer\Factory;
use Composer\IO\NullIO;
use Composer\Package\RootPackage;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\RepositoryManager;
use Mockery;
use Symfony\Component\Console\Output\StreamOutput;
use WyriHaximus\Composer\GenerativePluginTooling\GenerativePluginExecutioner;
use WyriHaximus\Composer\GenerativePluginTooling\Item as ItemContract;
use WyriHaximus\TestUtilities\TestCase;

use function fopen;
use function fseek;
use function json_encode;
use function stream_get_contents;
use function usort;

use const DIRECTORY_SEPARATOR;

final class GenerativePluginExecutionerTest extends TestCase
{
    /** @test */
    public function broadcast(): void
    {
        $composerConfig = new Config();
        $composerConfig->merge([
            'config' => [
                'vendor-dir' => __DIR__ . DIRECTORY_SEPARATOR . 'apps' . DIRECTORY_SEPARATOR . 'broadcast' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR,
            ],
        ]);
        $rootPackage = new RootPackage('wyrihaximus/broadcast', 'dev-master', 'dev-master');
        $rootPackage->setExtra([
            'wyrihaximus' => [
                'broadcast' => ['has-listeners' => true],
            ],
        ]);
        $rootPackage->setAutoload([
            'classmap' => ['dummy/event','dummy/listener/Listener.php'],
            'psr-4' => ['WyriHaximus\\Broadcast\\' => 'src'],
        ]);
        $io = new class () extends NullIO {
            private readonly StreamOutput $output;

            public function __construct()
            {
                /** @phpstan-ignore-next-line Let it blow */
                $this->output = new StreamOutput(fopen('php://memory', 'rw'), decorated: false);
            }

            public function output(): string
            {
                fseek($this->output->getStream(), 0);

                /** @phpstan-ignore-next-line Let it blow */
                return stream_get_contents($this->output->getStream());
            }

            /** @inheritDoc */
            public function write($messages, bool $newline = true, int $verbosity = self::NORMAL): void
            {
                $this->output->write($messages, $newline, $verbosity & StreamOutput::OUTPUT_RAW);
            }
        };

        $repository = Mockery::mock(InstalledRepositoryInterface::class);
        $repository->allows()->getCanonicalPackages();
        $repositoryManager = new RepositoryManager($io, $composerConfig, Factory::createHttpDownloader($io, $composerConfig));
        $repositoryManager->setLocalRepository($repository);
        $composer = new Composer();
        $composer->setConfig($composerConfig);
        $composer->setRepositoryManager($repositoryManager);
        $composer->setPackage($rootPackage);

        $plugin = new Plugin();
        GenerativePluginExecutioner::execute($composer, $io, $plugin);

        $output = $io->output();

        $items = [
            new Item(
                'WyriHaximus\\Broadcast\\Dummy\\Event',
                'WyriHaximus\\Broadcast\\Dummy\\Listener',
                'handle',
                false,
                false,
            ),
            new Item(
                'WyriHaximus\\Broadcast\\Dummy\\Event',
                'WyriHaximus\\Broadcast\\Dummy\\Listener',
                'handleBoth',
                false,
                false,
            ),
            new Item(
                'stdClass',
                'WyriHaximus\\Broadcast\\Dummy\\Listener',
                'handleBoth',
                false,
                false,
            ),
            new Item(
                'WyriHaximus\\Broadcast\\Dummy\\Event',
                'WyriHaximus\\Broadcast\\Dummy\\Listener',
                'doNotHandle',
                false,
                false,
            ),
            new Item(
                'WyriHaximus\\Broadcast\\Dummy\\Event',
                'WyriHaximus\\Broadcast\\Dummy\\AsyncListener',
                'handle',
                false,
                false,
            ),
        ];

        self::assertEquals([...self::sortItems(...$items)], [...self::sortItems(...$plugin->items)]);

        self::assertStringContainsString('<info>wyrihaximus/broadcast:</info> Locating listeners', $output);
        self::assertStringContainsString('<info>wyrihaximus/broadcast:</info> Found 5 listener(s)', $output);
        self::assertStringContainsString('<error>wyrihaximus/broadcast:</error> An error occurred: Cannot reflect "<fg=cyan>WyriHaximus\Broadcast\Dummy\BrokenAsyncListener</>": <fg=yellow>Roave\BetterReflection\Reflection\ReflectionClass "WyriHaximus\Broadcast\Contracts\AsyncListener" could not be found in the located source</>', $output);
        self::assertStringContainsString('<info>wyrihaximus/broadcast:</info> Generated static abstract listeners provider in', $output);
    }

    /** @return iterable<ItemContract> */
    private static function sortItems(ItemContract ...$items): iterable
    {
        usort($items, static fn (ItemContract $a, ItemContract $b): int => json_encode($a) <=> json_encode($b));

        yield from $items;
    }
}
