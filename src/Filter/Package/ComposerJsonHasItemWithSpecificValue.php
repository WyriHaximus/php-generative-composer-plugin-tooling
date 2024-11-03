<?php

declare(strict_types=1);

namespace WyriHaximus\Composer\GenerativePluginTooling\Filter\Package;

use Composer\Package\PackageInterface;
use WyriHaximus\Composer\GenerativePluginTooling\PackageFilter;

use function array_key_exists;
use function explode;
use function is_array;

final readonly class ComposerJsonHasItemWithSpecificValue implements PackageFilter
{
    private const KEY_NOT_FOUND_RETURN = false;

    /** @var array<string> */
    private array $keys;

    public function __construct(string $path, private mixed $value)
    {
        $this->keys = explode('.', $path);
    }

    public function __invoke(PackageInterface $package): bool
    {
        /**
         * Taken from https://github.com/igorw/get-in/blob/master/src/get_in.php#L5-L26
         * and put in here as composer doesn't like autoloading functions in plugins.
         *
         * @var array<string, string|bool|array<string, string|bool|array<string, string|bool|array<string, string|bool>>>> $current
         */
        $current = $package->getExtra();
        foreach ($this->keys as $key) {
            if (! is_array($current) || ! array_key_exists($key, $current)) {
                return self::KEY_NOT_FOUND_RETURN;
            }

            $current = $current[$key];
        }

        return $current === $this->value;
    }
}
