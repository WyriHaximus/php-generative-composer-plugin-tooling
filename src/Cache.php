<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling;

use JsonSerializable;

use function array_fill_keys;
use function array_is_list;
use function array_key_exists;
use function array_keys;
use function file_exists;
use function is_array;
use function is_bool;
use function is_string;
use function ksort;
use function md5;
use function md5_file;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

use const DIRECTORY_SEPARATOR;

final class Cache implements JsonSerializable
{
    /**
     * @param array<string, string>                                           $fileHashes
     * @param array<string, bool>                                             $classFilterOutcome
     * @param array<class-string, true>                                       $failedReflections
     * @param array<string, array<string, array<string, array<int, string>>>> $collectedItems
     * @param array<class-string, string>                                     $classFilePaths
     * @param array<string, bool>                                             $fileHashMatchMemo
     */
    public function __construct(
        private readonly string $root,
        private array $fileHashes,
        private array $classFilterOutcome,
        private array $failedReflections,
        private array $collectedItems,
        private array $classFilePaths,
        private string $installedJsonHash,
        private readonly bool $enabled,
        private array $fileHashMatchMemo,
    ) {
    }

    /** @param array<mixed> $json */
    public static function fromJSON(array $json, string $root): self
    {
        /** @var array<string, string> $fileHashes */
        $fileHashes = $json['fileHashes'] ?? [];

        /** @var array<string, bool> $classFilterOutcome */
        $classFilterOutcome = [];
        /** @var array<mixed> $rawClassFilterOutcome */
        $rawClassFilterOutcome = $json['classFilterOutcome'] ?? [];
        foreach ($rawClassFilterOutcome as $key => $entry) {
            if (! is_string($key)) {
                continue;
            }

            if (is_bool($entry)) {
                $classFilterOutcome[$key] = $entry;
                continue;
            }

            if (! is_array($entry) || ! is_bool($entry['outcome'] ?? null) || ! is_string($entry['class'] ?? null)) {
                continue;
            }

            if (is_string($entry['filterKey'] ?? null)) {
                $key = md5($entry['class'] . '_=_' . md5($entry['filterKey']));
            }

            $classFilterOutcome[$key] = $entry['outcome'];
        }

        /** @var list<class-string> $failedReflectionClassNames */
        $failedReflectionClassNames = $json['failedReflections'] ?? [];

        /** @var array<class-string, true> $failedReflections */
        $failedReflections = array_fill_keys($failedReflectionClassNames, true);

        /** @var array<string, array<string, array<string, array<int, string>>>> $collectedItems */
        $collectedItems = $json['collectedItems'] ?? [];

        /** @var array<class-string, string> $classFilePaths */
        $classFilePaths = $json['classFilePaths'] ?? [];

        $installedJsonHash = is_string($json['installedJsonHash'] ?? null) ? $json['installedJsonHash'] : '';

        return new self($root, $fileHashes, $classFilterOutcome, $failedReflections, $collectedItems, $classFilePaths, $installedJsonHash, true, []);
    }

    public static function disabled(): self
    {
        return new self('', [], [], [], [], [], '', false, []);
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
        $this->fileHashMatchMemo[$filePath]               = true;
    }

    public function fileHashMatches(string $filePath): bool
    {
        if (! $this->enabled || ! file_exists($filePath)) {
            return false;
        }

        if (array_key_exists($filePath, $this->fileHashMatchMemo)) {
            return $this->fileHashMatchMemo[$filePath];
        }

        $relativePath = $this->relativePath($filePath);
        if (! array_key_exists($relativePath, $this->fileHashes)) {
            return $this->fileHashMatchMemo[$filePath] = false;
        }

        return $this->fileHashMatchMemo[$filePath] = $this->fileHashes[$relativePath] === md5_file($filePath);
    }

    public function getClassFilterOutcome(string $class, string $filterKeyHash): bool|null
    {
        if (! $this->enabled) {
            return null;
        }

        $key = md5($class . '_=_' . $filterKeyHash);
        if (! array_key_exists($key, $this->classFilterOutcome)) {
            return null;
        }

        return $this->classFilterOutcome[$key];
    }

    public function classFilterOutcome(string $class, string $filterKeyHash, bool $outcome): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->classFilterOutcome[md5($class . '_=_' . $filterKeyHash)] = $outcome;
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

    /** @param class-string $class */
    public function classFilePath(string $class, string $filePath): void
    {
        if (! $this->enabled || $filePath === '') {
            return;
        }

        $this->classFilePaths[$class] = $this->relativePath($filePath);
    }

    /** @param class-string $class */
    public function getClassAbsoluteFilePath(string $class): string|null
    {
        if (! $this->enabled) {
            return null;
        }

        $relativePath = $this->classFilePaths[$class] ?? null;
        if ($relativePath === null) {
            return null;
        }

        return $this->normalizePath($this->root !== '' ? $this->root . $relativePath : $relativePath);
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
        /** @var array<string, mixed> $sorted */
        $sorted = self::ksortStringKeys([
            'installedJsonHash' => $this->installedJsonHash,
            'fileHashes' => $this->fileHashes,
            'classFilterOutcome' => $this->classFilterOutcome,
            'classFilePaths' => $this->classFilePaths,
            'failedReflections' => [...array_keys($this->failedReflections)],
            'collectedItems' => $this->collectedItems,
        ]);

        return $sorted;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    private static function ksortStringKeys(array $data): array
    {
        foreach ($data as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            $data[$key] = self::ksortStringKeys($value);
        }

        if ($data !== [] && ! array_is_list($data)) {
            ksort($data);
        }

        return $data;
    }

    private function relativePath(string $filePath): string
    {
        if ($this->root === '') {
            return $filePath;
        }

        $root           = $this->normalizePath($this->root);
        $normalizedPath = $this->normalizePath($filePath);

        if (! str_starts_with($normalizedPath, $root)) {
            return $filePath;
        }

        return substr($normalizedPath, strlen($root));
    }

    private function normalizePath(string $path): string
    {
        return str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);
    }
}
