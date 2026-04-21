<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling;

use JsonSerializable;

use function array_fill_keys;
use function array_key_exists;
use function array_keys;
use function file_exists;
use function is_string;
use function md5;
use function md5_file;
use function str_starts_with;
use function strlen;
use function substr;

final class Cache implements JsonSerializable
{
    /**
     * @param array<string, string>                                                    $fileHashes
     * @param array<string, array{class: string, filter: class-string, outcome: bool}> $classFilterOutcome
     * @param array<class-string, true>                                                $failedReflections
     * @param array<string, array<string, array<string, array<int, string>>>>          $collectedItems
     */
    public function __construct(
        private readonly string $root,
        private array $fileHashes,
        private array $classFilterOutcome,
        private array $failedReflections,
        private array $collectedItems,
        private string $installedJsonHash,
        private readonly bool $enabled,
    ) {
    }

    /** @param array<mixed> $json */
    public static function fromJSON(array $json, string $root): self
    {
        /** @var array<string, string> $fileHashes */
        $fileHashes = $json['fileHashes'] ?? [];

        /** @var array<string, array{class: string, filter: class-string, outcome: bool}> $classFilterOutcome */
        $classFilterOutcome = $json['classFilterOutcome'] ?? [];

        /** @var list<class-string> $failedReflectionClassNames */
        $failedReflectionClassNames = $json['failedReflections'] ?? [];

        /** @var array<class-string, true> $failedReflections */
        $failedReflections = array_fill_keys($failedReflectionClassNames, true);

        /** @var array<string, array<string, array<string, array<int, string>>>> $collectedItems */
        $collectedItems = $json['collectedItems'] ?? [];

        $installedJsonHash = is_string($json['installedJsonHash'] ?? null) ? $json['installedJsonHash'] : '';

        return new self($root, $fileHashes, $classFilterOutcome, $failedReflections, $collectedItems, $installedJsonHash, enabled: true);
    }

    public static function disabled(): self
    {
        return new self('', [], [], [], [], '', enabled: false);
    }

    /** @phpstan-ignore shipmonk.deadMethod */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function installedJsonHashMatches(string $installedJsonPath): bool
    {
        if (! $this->enabled || $this->installedJsonHash === '' || ! file_exists($installedJsonPath)) {
            return false;
        }

        return $this->installedJsonHash === md5_file($installedJsonPath);
    }

    public function setInstalledJsonHash(string $installedJsonPath): void
    {
        if (! $this->enabled || ! file_exists($installedJsonPath)) {
            return;
        }

        $hash = md5_file($installedJsonPath);
        if ($hash === false) {
            return;
        }

        $this->installedJsonHash = $hash;
    }

    public function fileHash(string $filePath, string $hash): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->fileHashes[$this->relativePath($filePath)] = $hash;
    }

    public function fileHashMatches(string $filePath): bool
    {
        if (! $this->enabled || ! file_exists($filePath)) {
            return false;
        }

        $relativePath = $this->relativePath($filePath);
        if (! array_key_exists($relativePath, $this->fileHashes)) {
            return false;
        }

        return $this->fileHashes[$relativePath] === md5_file($filePath);
    }

    /** @param class-string $filter */
    public function getClassFilterOutcome(string $class, string $filter): bool|null
    {
        if (! $this->enabled) {
            return null;
        }

        $key = md5($class . '_=_' . $filter);
        if (! array_key_exists($key, $this->classFilterOutcome)) {
            return null;
        }

        return $this->classFilterOutcome[$key]['outcome'];
    }

    /** @param class-string $filter */
    public function classFilterOutcome(string $class, string $filter, bool $outcome): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->classFilterOutcome[md5($class . '_=_' . $filter)] = [
            'class' => $class,
            'filter' => $filter,
            'outcome' => $outcome,
        ];
    }

    /** @param class-string $class */
    public function failedReflection(string $class): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->failedReflections[$class] = true;
    }

    /** @param class-string $class */
    public function hasFailedReflection(string $class): bool
    {
        if (! $this->enabled) {
            return false;
        }

        return array_key_exists($class, $this->failedReflections);
    }

    /**
     * @param array<int, string> $items
     * @param class-string       $collector
     */
    public function collectedItems(string $plugin, string $class, string $collector, array $items): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->collectedItems[$plugin][$class][$collector] = $items;
    }

    /**
     * @param class-string $collector
     *
     * @return array<int, string>|null
     */
    public function getCollectedItems(string $plugin, string $class, string $collector): array|null
    {
        if (! $this->enabled) {
            return null;
        }

        if (! $this->hasCollectedItems($plugin, $class, $collector)) {
            return null;
        }

        return $this->collectedItems[$plugin][$class][$collector];
    }

    /** @param class-string $collector */
    public function hasCollectedItems(string $plugin, string $class, string $collector): bool
    {
        if (! $this->enabled) {
            return false;
        }

        return array_key_exists($collector, $this->collectedItems[$plugin][$class] ?? []);
    }

    /** @param iterable<ItemCollector> $collectors */
    public function hasCollectedItemsForClass(string $plugin, string $class, iterable $collectors): bool
    {
        if (! $this->enabled) {
            return false;
        }

        foreach ($collectors as $collector) {
            if (! $this->hasCollectedItems($plugin, $class, $collector::class)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<class-string> */
    public function failedReflectionClasses(): array
    {
        return [...array_keys($this->failedReflections)];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'installedJsonHash' => $this->installedJsonHash,
            'fileHashes' => $this->fileHashes,
            'classFilterOutcome' => $this->classFilterOutcome,
            'failedReflections' => [...array_keys($this->failedReflections)],
            'collectedItems' => $this->collectedItems,
        ];
    }

    private function relativePath(string $filePath): string
    {
        if ($this->root !== '' && str_starts_with($filePath, $this->root)) {
            return substr($filePath, strlen($this->root));
        }

        return $filePath;
    }
}
