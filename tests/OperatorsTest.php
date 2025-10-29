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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\StreamOutput;
use WyriHaximus\Broadcast\Dummy\AsyncListener;
use WyriHaximus\Broadcast\Dummy\Event;
use WyriHaximus\Broadcast\Dummy\Listener;
use WyriHaximus\Composer\GenerativePluginTooling\GenerativePluginExecutioner;
use WyriHaximus\Composer\GenerativePluginTooling\Item as ItemContract;
use WyriHaximus\TestUtilities\TestCase;

use function fopen;
use function fseek;
use function json_encode;
use function stream_get_contents;
use function usort;

use const DIRECTORY_SEPARATOR;

final class OperatorsTest extends TestCase
{
    /** @return iterable<string, array<string>> */
    public static function apps(): iterable
    {
        yield 'broadcast' => ['broadcast'];
        yield 'broadcast-classmap' => ['broadcast-classmap'];
    }

    #[Test]
    #[DataProvider('apps')]
    public function broadcast(string $app): void
    {
        $composerConfig = new Config();
        $composerConfig->merge([
            'config' => [
                'vendor-dir' => __DIR__ . DIRECTORY_SEPARATOR . 'apps' . DIRECTORY_SEPARATOR . $app . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR,
            ],
        ]);
        $rootPackage = new RootPackage('wyrihaximus/makefiles', 'dev-master', 'dev-master');
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

                return stream_get_contents($this->output->getStream());
            }

            /**
             * @inheritDoc
             * @phpstan-ignore typeCoverage.paramTypeCoverage
             */
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
                Event::class,
                Listener::class,
                'handle',
                false,
                false,
            ),
            new Item(
                Event::class,
                Listener::class,
                'handleBoth',
                false,
                false,
            ),
            new Item(
                'stdClass',
                Listener::class,
                'handleBoth',
                false,
                false,
            ),
            new Item(
                Event::class,
                Listener::class,
                'doNotHandle',
                false,
                false,
            ),
            new Item(
                Event::class,
                AsyncListener::class,
                'handle',
                false,
                false,
            ),
        ];

        self::assertEquals([...$this->sortItems(...$items)], [...$this->sortItems(...$plugin->items())]);

        self::assertStringContainsString('<info>wyrihaximus/makefiles:</info> Locating listeners', $output);
        self::assertStringContainsString('<info>wyrihaximus/makefiles:</info> Found 5 listener(s)', $output);
        self::assertStringContainsString('<error>wyrihaximus/makefiles:</error> An error occurred: Cannot reflect "<fg=cyan>WyriHaximus\Broadcast\Dummy\BrokenAsyncListener</>": <fg=yellow>Roave\BetterReflection\Reflection\ReflectionClass "WyriHaximus\Broadcast\Contracts\AsyncListener" could not be found in the located source</>', $output);
        self::assertStringContainsString('<info>wyrihaximus/makefiles:</info> Generated static abstract listeners provider in', $output);
    }

    /** @return iterable<ItemContract> */
    private function sortItems(ItemContract ...$items): iterable
    {
        usort($items, static fn (ItemContract $a, ItemContract $b): int => (string) json_encode($a) <=> (string) json_encode($b));

        yield from $items;
    }
}
