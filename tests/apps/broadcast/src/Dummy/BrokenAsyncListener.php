<?php

declare(strict_types=1);

namespace WyriHaximus\Broadcast\Dummy;

use WyriHaximus\Broadcast\Contracts\AsyncListener as AsyncListenerContract;

/** @internal */
final class BrokenAsyncListener implements AsyncListenerContract
{
    /** @var callable $handler */
    private $handler;

    public function __construct(callable $handler)
    {
        $this->handler = $handler;
    }

    public function handle(Event $event): void
    {
        ($this->handler)($event);
    }
}