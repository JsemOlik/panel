<?php

namespace Pterodactyl\Services\ConsoleArchive;

use Pterodactyl\Models\ServerConsoleArchive;

/**
 * A single, already-classified console/chat line, ANSI-stripped and ready to persist.
 *
 * This is the unit both the persistence path (ConsoleLineBatcher -> ServerConsoleArchive::insert())
 * and the event payload (see \Pterodactyl\Events\ConsoleArchive\ConsoleLinesCaptured) are built
 * from. Anything consuming captured console output — the ingestion daemon itself, the eventual
 * keyword-alert listener, the eventual player-activity listener — should be able to work purely
 * off instances of this class without touching Wings, the websocket, or raw ANSI-laden text.
 */
final class CapturedConsoleLine
{
    public function __construct(
        public readonly int $serverId,
        public readonly \DateTimeImmutable $loggedAt,
        public readonly string $line,
        public readonly string $source = ServerConsoleArchive::SOURCE_CONSOLE,
        public readonly ?string $player = null,
    ) {
    }

    public function isChat(): bool
    {
        return $this->source === ServerConsoleArchive::SOURCE_CHAT;
    }

    /**
     * @return array{server_id: int, logged_at: \DateTimeImmutable, line: string, source: string, player: string|null}
     */
    public function toArray(): array
    {
        return [
            'server_id' => $this->serverId,
            'logged_at' => $this->loggedAt,
            'line' => $this->line,
            'source' => $this->source,
            'player' => $this->player,
        ];
    }
}
