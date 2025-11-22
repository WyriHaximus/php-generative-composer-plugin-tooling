# php-generative-composer-plugin-tooling

Tooling for creating generative Composer Plugins

![Continuous Integration](https://github.com/wyrihaximus/php-generative-composer-plugin-tooling/workflows/Continuous%20Integration/badge.svg)
[![Latest Stable Version](https://poser.pugx.org/wyrihaximus/generative-composer-plugin-tooling/v/stable.png)](https://packagist.org/packages/wyrihaximus/generative-composer-plugin-tooling)
[![Total Downloads](https://poser.pugx.org/wyrihaximus/generative-composer-plugin-tooling/downloads.png)](https://packagist.org/packages/wyrihaximus/generative-composer-plugin-tooling/stats)
[![Type Coverage](https://shepherd.dev/github/WyriHaximus/php-generative-composer-plugin-tooling/coverage.svg)](https://shepherd.dev/github/WyriHaximus/php-generative-composer-plugin-tooling)
[![License](https://poser.pugx.org/wyrihaximus/generative-composer-plugin-tooling/license.png)](https://packagist.org/packages/wyrihaximus/generative-composer-plugin-tooling)

The main goal of this package is to take the repetitive annoying parts composer plugins that generate code out of hands.
And make it so you can focus on the details. The usage example below is based on
[`wyrihaximus/broadcast`](https://github.com/wyrihaximus/php-broadcast) as it generates a listener provider based on marker interfaces:

We start with setting up our package to be a composer plugin and how composer can call the plugin:

```json
{
  "name": "wyrihaximus/broadcast",
  "type": "composer-plugin",
  "extra": {
    "class": "WyriHaximus\\Broadcast\\Composer\\Installer"
  },
  "scripts": {
    "pre-autoload-dump": [
      "WyriHaximus\\Broadcast\\Composer\\Installer::findEventListeners"
    ]
  }
}
```

We also have a pretty standard plugin class that delegates the work to the `GenerativePluginExecutioner`:

```php
<?php

declare(strict_types=1);

namespace WyriHaximus\Broadcast\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use WyriHaximus\Composer\GenerativePluginTooling\GenerativePluginExecutioner;

use const PHP_INT_MIN;

final class Installer implements PluginInterface, EventSubscriberInterface
{
    /**
     * @return array<string, array<string|int>>
     */
    public static function getSubscribedEvents(): array
    {
        return [ScriptEvents::PRE_AUTOLOAD_DUMP => ['findEventListeners', PHP_INT_MIN]];
    }

    public function activate(Composer $composer, IOInterface $io): void
    {
        // does nothing, see getSubscribedEvents() instead.
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // does nothing, see getSubscribedEvents() instead.
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // does nothing, see getSubscribedEvents() instead.
    }

    /**
     * Called before every dump autoload, generates a fresh PHP class.
     */
    public static function findEventListeners(Event $event): void
    {
        GenerativePluginExecutioner::execute($event->getComposer(), $event->getIO(), new Plugin());
    }
}
```

The plugin itself has two static and 3 non-static methods. The two static methods are purely for the name of the
package and provide a list of logging messages, where `%s` and `%d` are used for some information about what is
happening for how long and how many.

The non-static methods are where most of the magic happens. `filters` returns a list of class and package filters used
to figure out which packages to search for which classes.

`collectors` returns the collector or collectors used to figure out which classes to turn into `Item`'s to be passed
into `compile`.

Where `compile` takes a path and a list of `Item`'s and is meant to generate classes from the items it gets and write
that to disk:

```php
<?php

declare(strict_types=1);

namespace WyriHaximus\Broadcast\Composer;

use WyriHaximus\Broadcast\Contracts\AsyncListener;
use WyriHaximus\Broadcast\Contracts\Listener;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Class\ImplementsInterface;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Class\IsInstantiable;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Package\ComposerJsonHasItemWithSpecificValue;
use WyriHaximus\Composer\GenerativePluginTooling\GenerativePlugin;
use WyriHaximus\Composer\GenerativePluginTooling\Helper\TwigFile;
use WyriHaximus\Composer\GenerativePluginTooling\Item as ItemContract;
use WyriHaximus\Composer\GenerativePluginTooling\LogStages;

use function var_export;

final class Plugin implements GenerativePlugin
{
    public static function name(): string
    {
        return 'wyrihaximus/broadcast';
    }

    public static function log(LogStages $stage): string
    {
        return match ($stage) {
            LogStages::Init => 'Locating listeners',
            LogStages::Error => 'An error occurred: %s',
            LogStages::Collected => 'Found %d listener(s)',
            LogStages::Completion => 'Generated static abstract listeners provider in %s second(s)',
        };
    }

    /** @inheritDoc */
    public function filters(): iterable
    {
        yield new ComposerJsonHasItemWithSpecificValue('wyrihaximus.broadcast.has-listeners', true);
        yield new ImplementsInterface(Listener::class, AsyncListener::class);
        yield new IsInstantiable();
    }

    /** @inheritDoc */
    public function collectors(): iterable
    {
        yield new Collector();
    }

    public function compile(string $rootPath, ItemContract ...$items): void
    {
        $listeners = [];
        foreach ($items as $item) {
            if (! ($item instanceof Item)) {
                continue;
            }

            $listeners[$item->event][] = $item->jsonSerialize();
        }

        TwigFile::render(
            $rootPath . '/etc/AbstractListenerProvider.php.twig',
            $rootPath . '/src/Generated/AbstractListenerProvider.php',
            ['listeners' => var_export($listeners, true)],
        );
    }
}
```

The item object created by the collector, the details are all up to you. The `Item`
(aliased as `ItemContract` in the example) interface is purely a marker interface. We can JSON serialize it for the
specific use case of that package:

```php
<?php

declare(strict_types=1);

namespace WyriHaximus\Broadcast\Composer;

use JsonSerializable;
use WyriHaximus\Composer\GenerativePluginTooling\Item as ItemContract;

final readonly class Item implements ItemContract, JsonSerializable
{
    /** @param class-string $class */
    public function __construct(
        public string $event,
        public string $class,
        public string $method,
        public bool $static,
        public bool $async,
    ) {
    }

    /** @return array{event: string, class: class-string, method: string, static: bool, async: bool} */
    public function jsonSerialize(): array
    {
        return [
            'event' => $this->event,
            'class' => $this->class,
            'method' => $this->method,
            'static' => $this->static,
            'async' => $this->async,
        ];
    }
}
```

The collector takes a reflection class and figures out if it can be used. The filters used by the plugin makes sure we
only get classes implementing a certain marker interface. So from there we filter out only classes that have methods
with a single argument that is an object and consider that an event. So if it's just
`public function (ObjectA $event): void` that will be one item, but if it's
`public function (ObjectA|ObjectB $event): void` that will be two items. One for each possible event that might be
dispatched:

```php
<?php

declare(strict_types=1);

namespace WyriHaximus\Broadcast\Composer;

use Roave\BetterReflection\Reflection\ReflectionAttribute;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflection\ReflectionIntersectionType;
use Roave\BetterReflection\Reflection\ReflectionUnionType;
use WyriHaximus\Broadcast\Contracts\AsyncListener;
use WyriHaximus\Broadcast\Contracts\DoNotHandle;
use WyriHaximus\Composer\GenerativePluginTooling\Item as ItemContract;
use WyriHaximus\Composer\GenerativePluginTooling\ItemCollector;

use function array_map;
use function in_array;
use function strpos;

final class Collector implements ItemCollector
{
    /** @return iterable<ItemContract> */
    public function collect(ReflectionClass $class): iterable
    {
        foreach ($class->getMethods() as $method) {
            if (! $method->isPublic()) {
                continue;
            }

            if (strpos($method->getName(), '__') === 0) {
                continue;
            }

            if ($method->getNumberOfParameters() !== 1) {
                continue;
            }

            if (in_array(DoNotHandle::class, array_map(static fn (ReflectionAttribute $ra): string => $ra->getName(), $method->getAttributes()), true)) {
                continue;
            }

            $eventTypeHolder = $method->getParameters()[0]->getType();
            if ($eventTypeHolder instanceof ReflectionIntersectionType) {
                continue;
            }

            if ($eventTypeHolder instanceof ReflectionUnionType) {
                $eventTypes = $eventTypeHolder->getTypes();
            } else {
                $eventTypes = [$eventTypeHolder];
            }

            foreach ($eventTypes as $eventType) {
                yield new Item(
                    (string) $eventType,
                    $class->getName(),
                    $method->getName(),
                    $method->isStatic(),
                    $class->implementsInterface(AsyncListener::class),
                );
            }
        }
    }
}
```

# Build-in filters

## Package

### ComposerJsonHasItemWithSpecificValue

Only consider packages that have an item in its `composer.json` with a specific value.

So with the following arguments:

```php
new ComposerJsonHasItemWithSpecificValue('wyrihaximus.broadcast.has-listeners', true)
```

Only packages with this in their `composer.json` are considered:

```json
{
  "extra": {
    "wyrihaximus": {
        "broadcast": {
            "has-listeners": true
        }
    }
  }
}
```

## Class

### ImplementsInterface

Only consider classes that implement one of the interfaces passed into the constructor.

### IsInstantiable

Used to figure out if the class can be instantiated.

## Operator filters

Sometimes you want to collect multiple types of items. Or you want have a mixed set of conditions such as:

```php
yield from LogicalOr::create(
    new ImplementsInterface(Vhost::class),
    ...LogicalAnd::create(
        new IsInstantiable(),
        ...LogicalAnd::create(
            new HasAttributes(Attributes\Vhost::class),
            new HasAttributes(Attributes\Route::class),
        ),
    ),
);
```

In this situation we want to get all classes that either implement the `Vhost` interface, or that are instantiable and
have the `Vhost` and `Route` attributes. Operator filters accept both class and package filters, you can mix and match
them anyway you like. But as shown in the example above that return iterators, and internally they split them up in a
operator with only the package filters in them and one with only the class filters in them. So be sure to double check
what you are getting in the collector and compiler.

### Logical operators

By default, the following self-explanatory filters are included:

* LogicalAnd
* LogicalNot
* LogicalOr

# Helpers

## TwigFile

Found myself duplicating the following code a lot:

```php
$classContentsList = SimpleTwig::render(
    file_get_contents( /** @phpstan-ignore-line */
        $rootPath . '/etc/generated_templates/AbstractList.php.twig',
    ),
    ['workers' => $workers],
);
$installPathList   = $rootPath . '/src/Generated/AbstractList.php';
file_put_contents($installPathList, $classContentsList); /** @phpstan-ignore-line */
chmod($installPathList, 0664);
```

And honestly it made the code a lot less readable, especially when you have 4 in a row + a hydrator generating. So
taking that and putting it into the `TwigFIle` helper made the code a lot more readable:

```php
TwigFile::render(
    $rootPath . '/etc/generated_templates/AbstractList.php.twig',
    $rootPath . '/src/Generated/AbstractList.php',
    ['workers' => $workers],
);
```

## File

Takes a file name, it's contents, and the optional mode:

```php
File::write(
    $rootPath . '/src/Generated/AbstractList.php',
    'A List',
);
```

# Todo

- [X] Port boring bits from [`wyrihaximus/broadcast`](https://github.com/wyrihaximus/php-broadcast) for use in other packages
- [X] Build-in autoloader (sadly)
- [X] No userland functions anywhere (can't do that due to composer autoloader)
- [X] Helper to render twig files and write them out
- [X] Helper to write files
- [ ] Create parent directories for written files that don't exist yet
- [X] Support filtering on the attributes a class has
- [ ] Support filtering on the attributes a method in a has
- [X] Operator filters
- [ ] Improve performance
- [ ] Handle reflection errors better (which is a great part of the item above)

# Future goals/ideas

- [ ] Have a bin that can run the same generation so it can be ran outside of a composer cycle

# License

The MIT License (MIT)

Copyright (c) 2026 Cees-Jan Kiewiet

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
