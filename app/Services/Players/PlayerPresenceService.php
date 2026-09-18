<?php

namespace Pterodactyl\Services\Players;

use Pterodactyl\Models\Area;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerPlayer;
use Pterodactyl\Models\ServerPlayerSession;

/**
 * Applies a detected join/leave event to the presence tables: the current/last-known roster
 * (server_players) and the append-only history (server_player_sessions). See
 * PlayerPresenceParser for how a raw console line becomes one of these events.
 *
 * Idempotent by design: re-processing the same event twice (e.g. Wings replaying a backlog buffer
 * on reconnect) upserts the same roster state and appends one extra history row — harmless, and
 * cheaper to tolerate than to try to deduplicate, since the console archive itself makes no
 * stronger guarantee (see app/Services/ConsoleArchive/ConsoleLineBatcher.php).
 */
class PlayerPresenceService
{
    /**
     * Per-request cache of server_id => is this server a proxy, since a batch's lines all belong
     * to one server and this would otherwise run one extra query per line.
     *
     * @var array<int, bool>
     */
    private array $proxyCache = [];

    public function recordEvent(Server $server, string $event, string $player, \DateTimeImmutable $occurredAt): void
    {
        $occurredAtCarbon = \Carbon\Carbon::instance($occurredAt);

        ServerPlayerSession::query()->create([
            'server_id' => $server->id,
            'name' => $player,
            'event' => $event,
            'occurred_at' => $occurredAtCarbon,
        ]);

        /** @var ServerPlayer $row */
        $row = ServerPlayer::query()->firstOrNew([
            'server_id' => $server->id,
            'name' => $player,
        ]);

        // Out-of-order guard: batches are chronological within a server, but a line for this
        // exact player could in principle be re-delivered after a later one already applied (a
        // reconnect replay). Never let an older event overwrite fresher known state.
        if ($row->last_seen_at !== null && $occurredAtCarbon->lt($row->last_seen_at)) {
            return;
        }

        $row->status = $event === PlayerPresenceParser::EVENT_JOIN
            ? ServerPlayer::STATUS_ONLINE
            : ServerPlayer::STATUS_OFFLINE;

        if ($event === PlayerPresenceParser::EVENT_JOIN) {
            $row->joined_at = $occurredAtCarbon;
        }

        $row->last_seen_at = $occurredAtCarbon;
        $row->save();
    }

    /**
     * Whether the given server should be parsed with the proxy (BungeeCord/Waterfall) line shapes
     * rather than the backend (vanilla/Paper/Spigot) shapes.
     *
     * Primary signal is Area membership: a server attached to an area with pivot role
     * Area::ROLE_PROXY is, by this fork's own modelling of areas, the one proxy fronting that
     * area's backend servers (see app/Models/Area.php). This is checked first because it is an
     * explicit, admin-set fact rather than a guess.
     *
     * Fallback (a server not yet assigned to any area, or assigned only as a plain member):
     * the egg name containing "bungee" — this fork's seeded BungeeCord egg is literally named
     * "Bungeecord" (see tests/Traits/Integration/CreatesTestModels.php::getBungeecordEgg()) and a
     * Velocity egg would presumably follow the same naming convention, though Velocity's own log
     * format is out of scope here (see class docblock on PlayerPresenceParser).
     */
    public function isProxy(Server $server): bool
    {
        if (array_key_exists($server->id, $this->proxyCache)) {
            return $this->proxyCache[$server->id];
        }

        $isProxy = $server->areas()->wherePivot('role', Area::ROLE_PROXY)->exists()
            || str_contains(strtolower((string) optional($server->egg)->name), 'bungee')
            || str_contains(strtolower((string) optional($server->egg)->name), 'velocity');

        return $this->proxyCache[$server->id] = $isProxy;
    }
}
