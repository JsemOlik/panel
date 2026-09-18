<?php

namespace Pterodactyl\Listeners\Players;

use Throwable;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Services\Players\PlayerPresenceParser;
use Pterodactyl\Services\Players\PlayerPresenceService;
use Pterodactyl\Events\ConsoleArchive\ConsoleLinesCaptured;

/**
 * Derives join/leave presence from every captured console batch. Registered against
 * ConsoleLinesCaptured in app/Providers/EventServiceProvider.php.
 *
 * Dispatched synchronously on the ingestion daemon's own long-lived process (see that event's
 * docblock) — this listener MUST stay fast and MUST NOT throw, or it stalls console capture for
 * every server the daemon watches. Parsing a batch of lines is pure regex/string work (no I/O) and
 * the only writes are small, indexed upserts/inserts to the two presence tables, so this is safe to
 * run inline; anything heavier belongs in a queued job instead, not added here.
 */
class TrackPlayerPresenceListener
{
    public function __construct(private PlayerPresenceService $presence)
    {
    }

    public function handle(ConsoleLinesCaptured $event): void
    {
        try {
            $isProxy = $this->presence->isProxy($event->server);

            foreach ($event->lines as $line) {
                $parsed = PlayerPresenceParser::parse($line->line, $isProxy);

                if ($parsed === null) {
                    continue;
                }

                $this->presence->recordEvent(
                    $event->server,
                    $parsed['event'],
                    $parsed['player'],
                    $line->loggedAt,
                );
            }
        } catch (Throwable $exception) {
            // Never let a parsing/DB hiccup here stall console ingestion for this or any other
            // server — see class docblock. The archive itself already durably stored every raw
            // line regardless of what happens in this listener.
            Log::warning('Failed to process console batch for player presence tracking.', [
                'server_id' => $event->server->id,
                'exception' => $exception,
            ]);
        }
    }
}
