<?php

namespace Pterodactyl\Providers;

use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the ReactPHP event loop used by the console-archive ingestion daemon
 * (app/Console/Commands/ConsoleArchive/ConsumeConsoleArchiveCommand.php) so it can be
 * constructor-injected like everything else in this application, and swapped for a test double
 * in unit tests without touching a real event loop.
 *
 * A singleton is intentional: the whole point of `p:console-archive:consume` is to run one event
 * loop for the lifetime of the process, driving one websocket connection per server on it.
 */
class ConsoleArchiveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LoopInterface::class, fn () => Loop::get());
    }
}
