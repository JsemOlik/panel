<?php

namespace Pterodactyl\Services\Players;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerPlayer;
use Pterodactyl\Repositories\Wings\DaemonCommandRepository;

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

    public function __construct(private DaemonCommandRepository $repository)
    {
    }

    /**
     * Sends the action and returns the command that was issued, so the caller can record exactly
     * what was run in the activity log rather than a paraphrase of it.
     *
     * @throws \InvalidArgumentException when the target or text would not be safe to send
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException when the daemon is unreachable
     */
    public function send(Server $server, string $action, string $player, ?string $text = null): string
    {
        $command = $this->buildCommand($server, $action, $player, $text);

        $this->repository->setServer($server)->send($command);

        return $command;
    }

    public function buildCommand(Server $server, string $action, string $player, ?string $text = null): string
    {
        if (preg_match(self::NAME_PATTERN, $player) !== 1) {
            throw new \InvalidArgumentException('The player name contains characters that are not valid in a Minecraft username.');
        }

        // The target must be someone this server has actually seen. Without this the endpoint
        // would happily run `ban <anything matching the charset>`, which is a broader capability
        // than "moderate the players on my server" and leaves no trace of who was really meant.
        $known = ServerPlayer::query()->forServer($server)->where('name', $player)->exists();

        if (!$known) {
            throw new \InvalidArgumentException('That player has not been seen on this server.');
        }

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

        $text = trim($text);

        return $text === '' ? null : $text;
    }
}
