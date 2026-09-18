<?php

namespace Pterodactyl\Events\ConsoleArchive;

use Pterodactyl\Models\Server;
use Pterodactyl\Events\Event;
use Pterodactyl\Services\ConsoleArchive\CapturedConsoleLine;

/**
 * Dispatched once per flushed batch by the console-archive ingestion daemon, immediately after
 * that batch has been persisted to `server_console_archives`.
 *
 * ============================================================================================
 * THIS IS THE INTEGRATION POINT for downstream features built on top of console capture (e.g. a
 * player-activity panel, keyword alerts). Listen for this event rather than polling the
 * `server_console_archives` table or re-implementing any part of Wings ingestion.
 * ============================================================================================
 *
 * Contract:
 *  - `$server` is the fully-loaded Server model the batch belongs to. Every line in `$lines`
 *    belongs to this one server — batches are never mixed across servers.
 *  - `$lines` is a non-empty, chronologically-ordered array of CapturedConsoleLine value objects
 *    (app/Services/ConsoleArchive/CapturedConsoleLine.php). Each one exposes:
 *      - `serverId` (int)              — same as `$server->id`, included for convenience.
 *      - `loggedAt` (DateTimeImmutable) — when the daemon received the line, not insert time.
 *      - `line` (string)                — ANSI-stripped, control-character-stripped, truncated to
 *                                          config('console_archive.ingest.max_line_length').
 *      - `source` (string)              — ServerConsoleArchive::SOURCE_CONSOLE or ::SOURCE_CHAT.
 *                                          Heuristic, not authoritative — see
 *                                          ConsoleLineClassifier's docblock. Always read `line`
 *                                          regardless of `source` if you need every line, not
 *                                          just recognised chat.
 *      - `player` (string|null)         — best-effort parsed username for chat lines and
 *                                          join/leave lines. Null whenever it couldn't be parsed.
 *  - Listeners are invoked synchronously, on the ingestion daemon's own long-lived process (this
 *    event is NOT queued). A slow or throwing listener stalls ingestion for every server the
 *    daemon watches, so keep listeners fast and exception-safe — do expensive work by dispatching
 *    a queued job from inside the listener, don't do it inline.
 *  - The corresponding rows are queryable afterwards via, e.g.:
 *      ServerConsoleArchive::forServer($server)
 *          ->whereBetween('logged_at', [$first->loggedAt, $last->loggedAt])
 *          ->orderBy('id')->get();
 *    but the event payload itself already carries everything a listener needs for line-by-line
 *    processing — prefer it over a follow-up query.
 */
class ConsoleLinesCaptured extends Event
{
    /**
     * @param non-empty-array<int, CapturedConsoleLine> $lines
     */
    public function __construct(
        public readonly Server $server,
        public readonly array $lines,
    ) {
    }
}
