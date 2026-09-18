<?php

namespace Pterodactyl\Services\Players;

/**
 * Best-effort detection of join/leave events from a single (already ANSI-stripped) console line.
 *
 * This exists separately from, and is more specific than,
 * Pterodactyl\Services\ConsoleArchive\ConsoleLineClassifier: that classifier's `player` field is a
 * generic "who does this line concern" guess shared across chat/join/leave, and it only recognises
 * the vanilla/Paper/Spigot join/leave shape. Presence tracking needs a strict, unambiguous
 * join-or-leave-or-nothing decision, and it needs to handle a BungeeCord/Waterfall proxy, whose log
 * format is entirely different from a backend Minecraft server's and which this fork's classifier
 * does not attempt.
 *
 * A player connected to the proxy is on exactly one backend server at a time: the proxy's own
 * connect/disconnect lines mean "joined/left the network", while a backend server's join/leave
 * lines mean "joined/left that specific server". Both are real events worth recording distinctly,
 * so the caller must say which kind of egg produced the line (see PlayerPresenceService, which
 * decides this per-server) rather than this class guessing from content.
 *
 * Unrecognised lines (including a proxy's per-backend switch lines, and anything from a modded/
 * heavily plugin-altered server) return null and are otherwise ignored — never guessed at, and
 * never thrown on. The raw line is already durably stored by the console archive regardless of
 * whether this class can parse it; this is presence-derived convenience on top, not the source of
 * truth for "what happened".
 */
final class PlayerPresenceParser
{
    public const EVENT_JOIN = 'join';
    public const EVENT_LEAVE = 'leave';

    /**
     * Same optional `[HH:MM:SS]` / `[HH:MM:SS LEVEL]` timestamp, optionally followed by a
     * `[Thread/LEVEL]:` logger tag, that ConsoleLineClassifier strips. Kept independent (not
     * reused) so a change to the archive's chat-focused classifier can't silently change presence
     * detection. Used only for backend (vanilla/Paper/Spigot) lines.
     */
    private const PREFIX_PATTERN_BACKEND = '/^\[\d{2}:\d{2}:\d{2}(?:\s+\w+)?\]:?\s*(?:\[[^\]]*\]:?\s*)?/';

    /**
     * Proxy lines strip only the leading `[HH:MM:SS]` / `[HH:MM:SS LEVEL]` timestamp — unlike the
     * backend pattern above, there is no second generic bracket group to optionally swallow, since
     * on a proxy that next bracket IS the `[<player>]` the connect/disconnect patterns below need
     * to see.
     */
    private const PREFIX_PATTERN_PROXY = '/^\[\d{2}:\d{2}:\d{2}(?:\s+\w+)?\]:?\s*/';

    /** Vanilla/Paper/Spigot: the chat-visible broadcast, present on every stock build. */
    private const BACKEND_JOIN_BROADCAST = '/^([A-Za-z0-9_]{1,16}) joined the game$/';

    /**
     * Vanilla/Paper/Spigot: the network-thread line emitted unconditionally on login, independent
     * of whether the "joined the game" chat broadcast is visible/suppressed by a plugin. Kept as a
     * second, additive signal for reliability — either pattern matching is sufficient to record a
     * join, and a duplicate (both firing for the same login) is harmless: PlayerPresenceService
     * upserts idempotently.
     */
    private const BACKEND_JOIN_ENTITY = '/^([A-Za-z0-9_]{1,16})\[\/[^\]]*\] logged in with entity id \d+/';

    private const BACKEND_LEAVE_BROADCAST = '/^([A-Za-z0-9_]{1,16}) left the game$/';

    /**
     * Vanilla/Paper/Spigot: the network-thread disconnect line, emitted whether or not the "left
     * the game" broadcast is visible. This is the leave-side counterpart to BACKEND_JOIN_ENTITY
     * and exists for the same reason — without it, a build that decorates or suppresses the chat
     * broadcast produces joins (which BACKEND_JOIN_ENTITY still catches) but never leaves, and
     * every player is left showing online forever.
     */
    private const BACKEND_LEAVE_DISCONNECT = '/^([A-Za-z0-9_]{1,16}) lost connection:/';

    /**
     * Newer Paper builds log the join/leave broadcast through the system-chat path, which prefixes
     * the message with a literal "System chat: ". Stripped before matching so the broadcast
     * patterns work on both shapes rather than only the older one.
     */
    private const BACKEND_SYSTEM_CHAT_PREFIX = '/^System chat:\s*/';

    /**
     * BungeeCord/Waterfall: proxy-level connect/disconnect, logged by UserConnection as
     * `[<name>] has connected` / `[<name>] has disconnected`. Anchored at both ends deliberately:
     * a per-backend server switch is logged as `[<name>] -> <ServerName> has connected` /
     * `[<name>] -> <ServerName> has disconnected` (via ServerConnector), which must NOT be treated
     * as a network join/leave — the trailing `-> ServerName` before "has connected/disconnected"
     * means it simply fails to match either anchored pattern below and is correctly ignored here.
     */
    private const PROXY_CONNECT = '/^\[([A-Za-z0-9_]{1,16})\] has connected$/';
    private const PROXY_DISCONNECT = '/^\[([A-Za-z0-9_]{1,16})\] has disconnected$/';

    /**
     * @return array{event: string, player: string}|null
     */
    public static function parse(string $strippedLine, bool $isProxy): ?array
    {
        $prefixPattern = $isProxy ? self::PREFIX_PATTERN_PROXY : self::PREFIX_PATTERN_BACKEND;
        $remainder = preg_replace($prefixPattern, '', $strippedLine, 1) ?? $strippedLine;
        $remainder = trim($remainder);

        if ($remainder === '') {
            return null;
        }

        return $isProxy
            ? self::parseProxyLine($remainder)
            : self::parseBackendLine($remainder);
    }

    /**
     * @return array{event: string, player: string}|null
     */
    private static function parseBackendLine(string $remainder): ?array
    {
        $remainder = preg_replace(self::BACKEND_SYSTEM_CHAT_PREFIX, '', $remainder, 1) ?? $remainder;

        if (preg_match(self::BACKEND_JOIN_BROADCAST, $remainder, $matches) === 1
            || preg_match(self::BACKEND_JOIN_ENTITY, $remainder, $matches) === 1
        ) {
            return ['event' => self::EVENT_JOIN, 'player' => $matches[1]];
        }

        if (preg_match(self::BACKEND_LEAVE_BROADCAST, $remainder, $matches) === 1
            || preg_match(self::BACKEND_LEAVE_DISCONNECT, $remainder, $matches) === 1
        ) {
            return ['event' => self::EVENT_LEAVE, 'player' => $matches[1]];
        }

        return null;
    }

    /**
     * @return array{event: string, player: string}|null
     */
    private static function parseProxyLine(string $remainder): ?array
    {
        if (preg_match(self::PROXY_CONNECT, $remainder, $matches) === 1) {
            return ['event' => self::EVENT_JOIN, 'player' => $matches[1]];
        }

        if (preg_match(self::PROXY_DISCONNECT, $remainder, $matches) === 1) {
            return ['event' => self::EVENT_LEAVE, 'player' => $matches[1]];
        }

        return null;
    }
}
