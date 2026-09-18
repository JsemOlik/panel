<?php

namespace Pterodactyl\Services\ConsoleArchive;

use Carbon\CarbonImmutable;
use Pterodactyl\Enum\JwtScope;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Nodes\NodeJWTService;

/**
 * Mints the Wings websocket JWTs the ingestion daemon authenticates with, and resolves the
 * websocket URL to connect to — the same two things WebsocketController does for a real browser
 * session (app/Http/Controllers/Api/Client/Servers/WebsocketController.php), but for the daemon
 * itself rather than an end user.
 *
 * Important correction vs. the initial plan for this feature: NodeJWTService::setUser() is
 * optional to *call* (it only affects whether a `user_uuid` claim gets attached), but Wings does
 * NOT treat a missing `user_uuid` claim as "no user restriction" — router/tokens/websocket.go's
 * isDenylisted() treats an EMPTY user_uuid as unconditionally denylisted:
 *
 *   if payload.IssuedAt == nil || serverUUID == "" || userUUID == "" { return true }
 *
 * So a token with no user_uuid claim is rejected by Wings on every connection attempt. Since the
 * daemon has no real end user, it claims a fixed, non-nil synthetic UUID (SYNTHETIC_USER_UUID)
 * instead of calling setUser() with a real User model. That UUID never corresponds to an actual
 * subuser, so it can never be caught by DenyForServer()'s per-user revocation path (which Wings
 * only exercises for real user/server pairs the Panel explicitly revokes) — the only way the
 * daemon's tokens are invalidated is the same way every token is: JWT expiry, or Wings' own
 * boot-time denylist after a Wings restart, which the daemon already has to reconnect-with-backoff
 * around regardless.
 *
 * Permission claim: only `websocket.connect` is requested (Wings' PermissionConnect). Reading
 * `console output` itself is not gated behind any additional per-permission check in Wings
 * (router/websocket/websocket.go's SendJson only special-cases install/backup/transfer output) —
 * so the daemon does not need, and deliberately does not request, `control.console` or any other
 * elevated permission it doesn't use.
 */
final class WingsConsoleTokenBroker
{
    public const SYNTHETIC_USER_UUID = '00000000-0000-0000-0000-000000000000';

    public function __construct(private readonly NodeJWTService $jwtService)
    {
    }

    public function mintToken(Server $server): string
    {
        $node = $server->node;

        $token = $this->jwtService
            ->setExpiresAt(CarbonImmutable::now()->addMinutes((int) config('console_archive.ingest.token_ttl_minutes', 55)))
            ->setClaims([
                'server_uuid' => $server->uuid,
                'user_uuid' => self::SYNTHETIC_USER_UUID,
                'permissions' => ['websocket.connect'],
            ])
            ->setScopes(JwtScope::Websocket)
            ->handle($node, 'console-archive:' . $server->uuid);

        return $token->toString();
    }

    public function socketUrl(Server $server): string
    {
        $node = $server->node;
        $socket = str_replace(['https://', 'http://'], ['wss://', 'ws://'], $node->getConnectionAddress());

        return $socket . sprintf('/api/servers/%s/ws', $server->uuid);
    }
}
