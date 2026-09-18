<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerPlayer;
use Pterodactyl\Models\ServerPlayerSession;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Transformers\Api\Client\ServerPlayerTransformer;
use Pterodactyl\Transformers\Api\Client\ServerPlayerSessionTransformer;
use Pterodactyl\Http\Requests\Api\Client\Servers\GetServerPlayersRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\GetServerPlayerSessionsRequest;

/**
 * Serves the player presence roster and per-player join/leave history derived from console
 * capture — see app/Services/Players/PlayerPresenceService.php and
 * app/Listeners/Players/TrackPlayerPresenceListener.php for how these rows are produced.
 *
 * Access control: gated by Permission::ACTION_PLAYERS_READ via the two request classes, enforced
 * through ServerPolicy exactly like every other server-scoped client endpoint — a root admin or
 * the server owner always has access, a subuser must be explicitly granted `players.read`.
 */
class PlayerController extends ClientApiController
{
    /**
     * Returns the current roster for this server: every player ever observed, ordered online
     * first then by most recently seen, optionally filtered to just online/offline.
     */
    public function index(GetServerPlayersRequest $request, Server $server): array
    {
        $validated = $request->validated();

        $query = ServerPlayer::query()->forServer($server);

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $players = $query
            ->orderByDesc('status')
            ->orderByDesc('last_seen_at')
            ->get();

        return $this->fractal->collection($players)
            ->transformWith($this->getTransformer(ServerPlayerTransformer::class))
            ->toArray();
    }

    /**
     * Returns the join/leave history for a single named player on this server, newest first —
     * the "what has this player been doing" activity feed for the player-centric view.
     */
    public function sessions(GetServerPlayerSessionsRequest $request, Server $server, string $player): array
    {
        $validated = $request->validated();

        $sessions = ServerPlayerSession::query()
            ->forServer($server)
            ->forPlayer($player)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(min((int) ($validated['per_page'] ?? 50), 100))
            ->appends($request->query());

        return $this->fractal->collection($sessions)
            ->transformWith($this->getTransformer(ServerPlayerSessionTransformer::class))
            ->toArray();
    }
}
