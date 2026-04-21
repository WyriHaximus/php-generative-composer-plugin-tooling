<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling;

use JsonSerializable;

use function str_starts_with;
use function strlen;
use function substr;

final class Cache implements JsonSerializable
{
    /** @param array|array{fileHashes: array<string, string>, classFilterOutcome: array<string, array<string, class-string|bool>>} $json */
    public static function fromJSON(array $json, string $root): Cache
    {
        return new self($root, $json['fileHashes'] ?? [], $json['classFilterOutcome'] ?? []);
    }

    /**
     * @param array<string, string> $fileHashes
     * @param array<string, array<string, class-string|bool>> $classFilterOutcome
     */
    public function __construct(private readonly string $root, private array $fileHashes, private array $classFilterOutcome)
    {
    }

    public function fileHash(string $filePath, string $hash): void
    {
        if (str_starts_with($filePath, $this->root)) {
            $filePath = substr($filePath, strlen($this->root));
        }

        $this->fileHashes[$filePath] = $hash;
    }

    public function classFilterOutcome(string $class, string $filter, bool $outcome): void
    {
        $this->classFilterOutcome[md5($class . '_=_' . $filter)] = [
            'class' => $class,
            'filter' => $filter,
            'outcome' => $outcome,
        ];
    }

    /** @return array{fileHashes: array<string, string>} */
    public function jsonSerialize(): array
    {
        return [
            'fileHashes' => $this->fileHashes,
            'classFilterOutcome' => $this->classFilterOutcome,
        ];
    }
}
