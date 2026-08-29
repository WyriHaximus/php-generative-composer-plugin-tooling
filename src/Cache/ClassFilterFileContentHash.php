<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling\Cache;

use ReflectionClass as NativeReflectionClass;
use ReflectionObject;
use WyriHaximus\Composer\GenerativePluginTooling\ClassFilter;

use function file_exists;
use function implode;
use function is_string;
use function ksort;
use function md5;
use function md5_file;

final class ClassFilterFileContentHash
{
    /**
     * @param list<ClassFilter> $classFilters
     *
     * @phpstan-ignore shipmonk.deadMethod
     */
    public static function hash(array $classFilters): string
    {
        $fileHashes = [];
        foreach ($classFilters as $filter) {
            $fileHashes = self::collect($filter, $fileHashes);
        }

        ksort($fileHashes);

        return md5(implode('', $fileHashes));
    }

    /**
     * @param array<string, string> $fileHashes
     *
     * @return array<string, string>
     */
    private static function collect(ClassFilter $filter, array $fileHashes): array
    {
        $reflection = new NativeReflectionClass($filter);
        $file       = $reflection->getFileName();
        if (is_string($file) && file_exists($file)) {
            $hash = md5_file($file);
            if (is_string($hash)) {
                $fileHashes[$file] = $hash;
            }
        }

        $filterReflection = new ReflectionObject($filter);
        if ($filterReflection->hasProperty('filters')) {
            $filtersProperty = $filterReflection->getProperty('filters');
            /** @var array<ClassFilter> $nestedFilters */
            $nestedFilters = $filtersProperty->getValue($filter);
            foreach ($nestedFilters as $nestedFilter) {
                $fileHashes = self::collect($nestedFilter, $fileHashes);
            }
        }

        if (! $filterReflection->hasProperty('filter')) {
            return $fileHashes;
        }

        $filterProperty = $filterReflection->getProperty('filter');
        $nestedFilter   = $filterProperty->getValue($filter);
        if (! ($nestedFilter instanceof ClassFilter)) {
            return $fileHashes;
        }

        return self::collect($nestedFilter, $fileHashes);
    }
}
