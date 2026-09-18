<?php

namespace Pterodactyl\Services\ConsoleArchive;

use Pterodactyl\Models\ServerConsoleArchive;

/**
 * Best-effort classification of an (already ANSI-stripped) console line into "chat" vs. plain
 * "console" output, plus, where recognisable, the player it concerns.
 *
 * This is heuristic, not authoritative, by design: modified server jars, plugin-prefixed chat
 * (e.g. Discord bridges, rank prefixes), and non-vanilla formats will simply fail to classify and
 * fall back to `source = console, player = null`. The raw line is never discarded or altered based
 * on classification — callers that need "every line", not just "recognised chat", should read
 * `line` regardless of `source`.
 *
 * Recognises the vanilla/Paper/Spigot/Bungee/Velocity log4j-style line shape:
 *   [12:34:56] [Server thread/INFO]: <PlayerName> message text
 *   [12:34:56 INFO]: <PlayerName> message text
 * and, off the remainder after that optional timestamp/logger prefix:
 *   - `<Name> message`                 -> chat, player = Name
 *   - `Name joined the game`           -> console, player = Name (useful for player-activity tracking)
 *   - `Name left the game`             -> console, player = Name
 */
final class ConsoleLineClassifier
{
    /**
     * Matches a leading `[HH:MM:SS]` or `[HH:MM:SS LEVEL]` timestamp, optionally followed by a
     * `[Thread/LEVEL]:` logger tag. Both are optional and independently present/absent across
     * eggs, so each half of the prefix is its own optional group.
     */
    private const PREFIX_PATTERN = '/^\[\d{2}:\d{2}:\d{2}(?:\s+\w+)?\]:?\s*(?:\[[^\]]*\]:?\s*)?/';

    private const CHAT_PATTERN = '/^<([^>]{1,64})>\s?(.*)$/s';

    private const JOIN_PATTERN = '/^([A-Za-z0-9_]{1,32}) (?:joined the game)$/';

    private const LEAVE_PATTERN = '/^([A-Za-z0-9_]{1,32}) (?:left the game)$/';

    /**
     * @return array{source: string, player: string|null}
     */
    public static function classify(string $strippedLine): array
    {
        $remainder = preg_replace(self::PREFIX_PATTERN, '', $strippedLine, 1) ?? $strippedLine;
        $remainder = trim($remainder);

        if (preg_match(self::CHAT_PATTERN, $remainder, $matches) === 1) {
            return ['source' => ServerConsoleArchive::SOURCE_CHAT, 'player' => $matches[1]];
        }

        if (preg_match(self::JOIN_PATTERN, $remainder, $matches) === 1
            || preg_match(self::LEAVE_PATTERN, $remainder, $matches) === 1
        ) {
            return ['source' => ServerConsoleArchive::SOURCE_CONSOLE, 'player' => $matches[1]];
        }

        return ['source' => ServerConsoleArchive::SOURCE_CONSOLE, 'player' => null];
    }
}
