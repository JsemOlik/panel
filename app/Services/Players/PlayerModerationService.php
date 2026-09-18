<?php

namespace Pterodactyl\Services\Players;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerPlayer;
use Pterodactyl\Repositories\Wings\DaemonCommandRepository;
use Pterodactyl\Services\Players\PlayerPresenceService;

/**
 * Turns a moderation request against a named player into a single console command.
 *
 * Everything here exists because this endpoint hands a narrow permission
 * (Permission::ACTION_PLAYERS_MODERATE) the ability to write to a console that normally requires
 * the much broader control.console. The whole value of that split disappears if a crafted name or
 * message can smuggle in a second command, so the inputs are constrained rather than escaped:
 *
 *  - the player name must match the same conservative charset the presence parser accepts, AND
 *    already exist on this server's roster, so a caller cannot invent a target;
 *  - free text may not contain a newline or carriage return. A console connection is line
 *    oriented, so "hello\nop attacker" is two commands, and a single missed newline would turn
 *    "may message players" into "may do anything". Rejecting is correct here rather than
 *    stripping: a message that silently loses half its content is its own failure, and a caller
 *    sending one has either made a mistake worth surfacing or is probing.
 */
class PlayerModerationService
{
    public const ACTION_MESSAGE = 'message';
    public const ACTION_KICK = 'kick';
    public const ACTION_BAN = 'ban';

    /**
     * The charset PlayerPresenceParser recognises. Minecraft usernames are [A-Za-z0-9_]{3,16}.
     */
    public const NAME_PATTERN = '/^[A-Za-z0-9_]{1,16}$/';

    /**
     * Mirrored by SendPlayerActionRequest's `max:` rule, which references this constant rather
     * than repeating the number — see the class docblock on why the cap must hold here too rather
     * than only in the request class.
     */
    public const TEXT_MAX_LENGTH = 256;

    public function __construct(
        private DaemonCommandRepository $repository,
        private PlayerPresenceService $presence,
    ) {
    }

    /**
     * Sends the action and returns the command that was issued plus the canonical roster name it
     * targeted, so the caller can record exactly what was run — and against whom — in the activity
     * log rather than a paraphrase of it.
     *
     * @return array{command: string, player: string}
     *
     * @throws \InvalidArgumentException when the target or text would not be safe to send
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException when the daemon is unreachable
     */
    public function send(Server $server, string $action, string $player, ?string $text = null): array
    {
        $player = $this->resolveRosterName($server, $player);
        $command = $this->buildCommand($server, $action, $player, $text);

        $this->repository->setServer($server)->send($command);

        return ['command' => $command, 'player' => $player];
    }

    public function buildCommand(Server $server, string $action, string $player, ?string $text = null): string
    {
        // Proxy consoles (BungeeCord/Velocity) don't speak `tell`/`kick`/`ban` — those are backend
        // Minecraft commands. Wings will happily "succeed" sending a string a proxy doesn't
        // recognise, so this has to be caught here rather than trusted to a successful send.
        if ($this->presence->isProxy($server)) {
            throw new \InvalidArgumentException('This server is a proxy. Message, kick and ban have to be run on the backend server the player is actually connected to — the panel cannot know which commands a given proxy build supports.');
        }

        $player = $this->resolveRosterName($server, $player);
        $text = $this->sanitiseText($text);

        if ($action === self::ACTION_MESSAGE && $text === null) {
            throw new \InvalidArgumentException('A message is required.');
        }

        return match ($action) {
            self::ACTION_MESSAGE => sprintf('tell %s %s', $player, $text),
            // A reason is optional for both: vanilla accepts bare `kick <player>` / `ban <player>`.
            self::ACTION_KICK => trim(sprintf('kick %s %s', $player, $text ?? '')),
            self::ACTION_BAN => trim(sprintf('ban %s %s', $player, $text ?? '')),
            default => throw new \InvalidArgumentException('Unknown player action.'),
        };
    }

    /**
     * Looks up the roster row rather than merely checking existence, and returns its stored
     * casing. server_players is utf8mb4_unicode_ci, so a lookup for "jsemolik" matches the row
     * "JsemOlik" — using the caller's casing from there on would send the console a name that
     * doesn't match what anything else (the log, a plugin matching names exactly) knows the player
     * as. Everything downstream of this call must use the returned name, not the input.
     */
    private function resolveRosterName(Server $server, string $player): string
    {
        if (preg_match(self::NAME_PATTERN, $player) !== 1) {
            throw new \InvalidArgumentException('The player name contains characters that are not valid in a Minecraft username.');
        }

        // The target must be someone this server has actually seen. Without this the endpoint
        // would happily run `ban <anything matching the charset>`, which is a broader capability
        // than "moderate the players on my server" and leaves no trace of who was really meant.
        $row = ServerPlayer::query()->forServer($server)->where('name', $player)->first();

        if ($row === null) {
            throw new \InvalidArgumentException('That player has not been seen on this server.');
        }

        return $row->name;
    }

    private function sanitiseText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        // Not str_replace: see the class docblock on why a line break is refused outright rather
        // than quietly removed.
        if (preg_match('/[\r\n]/', $text) === 1) {
            throw new \InvalidArgumentException('Messages and reasons may not contain line breaks.');
        }

        // This class's own docblock says it is the guarantee that does not depend on every future
        // caller going through SendPlayerActionRequest — the same reasoning that makes the newline
        // check above unconditional applies to the length cap the request class also enforces.
        if (mb_strlen($text) > self::TEXT_MAX_LENGTH) {
            throw new \InvalidArgumentException(sprintf('Messages and reasons may not be longer than %d characters.', self::TEXT_MAX_LENGTH));
        }

        $text = trim($text);

        return $text === '' ? null : $text;
    }
}
