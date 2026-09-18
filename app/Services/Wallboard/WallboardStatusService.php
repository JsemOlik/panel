<?php

namespace Pterodactyl\Services\Wallboard;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Pterodactyl\Models\Area;
use Pterodactyl\Models\Server;
use Illuminate\Cache\Repository;
use Pterodactyl\Models\ServerResourceSample;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Builds the per-area/per-server payload the wallboard renders, in a single pass per request —
 * this is the "aggregate endpoint" the wallboard polls instead of opening N per-server
 * connections (see AreaWallboardController for the data-source reasoning).
 *
 * Live status re-uses the EXACT SAME cache key ("resources:$uuid", 20s TTL) that
 * ResourceUtilizationController already warms for the single-server "current usage" widget.
 * This is deliberate: a wallboard poll costs Wings nothing extra beyond what staff already
 * trigger by having a server's console open, and multiple wallboard screens polling at once
 * still only ever cost the cluster one Wings call per server per 20 seconds, cache-wide — not
 * one call per viewer.
 *
 * A failed Wings call is itself cached for a short window ("resources:down:$uuid") so that a
 * node being unreachable for an extended period does not turn into a hot retry loop: at most
 * one failed attempt per server every 20 seconds, cluster-wide, for as long as the outage lasts.
 *
 * When live data is unavailable, this falls back to the most recent
 * `server_resource_samples` row (written by the resource-stats collector, see
 * App\Console\Commands\Maintenance\CollectResourceStatsCommand) so the wallboard can show
 * "last known state as of HH:MM" instead of nothing — but it always marks that response
 * `stale => true` so the frontend is required to render it as visibly non-live. A sample older
 * than STALE_SAMPLE_MAX_AGE_MINUTES is treated as no better than having nothing at all, and the
 * server is reported `unreachable => true` instead.
 */
class WallboardStatusService
{
    /**
     * How long a live Wings response is trusted before this service will attempt to refresh it.
     * Matches the TTL ResourceUtilizationController already uses for the same cache key.
     */
    private const LIVE_CACHE_SECONDS = 20;

    /**
     * How long a failed Wings call is remembered before another attempt is allowed for that
     * server. Deliberately equal to LIVE_CACHE_SECONDS so a recovered node is picked back up on
     * the very next cache window rather than being held down artificially.
     */
    private const DOWN_CACHE_SECONDS = 20;

    /**
     * A stored resource sample older than this is not a meaningful "last known state" any more —
     * the collector polls once a minute, so anything past a few missed polls means the collector
     * itself has lost the server too, not just this request.
     */
    private const STALE_SAMPLE_MAX_AGE_MINUTES = 5;

    public function __construct(
        private Repository $cache,
        private DaemonServerRepository $daemonServerRepository,
    ) {
    }

    /**
     * Transforms a single area (with its `servers.node` relation already eager loaded by the
     * caller) into the wallboard's wire shape, ordering members the same way AreaTransformer
     * does: members in their configured start order, proxy last.
     *
     * @return array<string, mixed>
     */
    public function transformArea(Area $area): array
    {
        $members = $area->servers
            ->sortBy(fn (Server $server) => $this->pivotAttribute($server, 'role') === Area::ROLE_PROXY
                ? PHP_INT_MAX
                : $this->pivotAttribute($server, 'sort_order'))
            ->map(fn (Server $server) => $this->transformServer($server))
            ->values()
            ->all();

        return [
            'id' => $area->id,
            'uuid' => $area->uuid,
            'name' => $area->name,
            'description' => $area->description,
            'members' => $members,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformServer(Server $server): array
    {
        $status = $this->statusFor($server);

        return [
            'id' => $server->id,
            'uuid' => $server->uuid,
            'name' => $server->name,
            'role' => $this->pivotAttribute($server, 'role'),
            'sort_order' => $this->pivotAttribute($server, 'sort_order'),
            'is_node_under_maintenance' => $server->node->isUnderMaintenance(),
        ] + $status;
    }

    /**
     * Resolves the live-or-fallback status for one server. Never throws — every failure mode
     * (Wings unreachable, no cached sample, panel-suspended server) resolves to a normalized
     * shape the frontend can render without a null check on every field.
     *
     * @return array<string, mixed>
     */
    private function statusFor(Server $server): array
    {
        if ($server->isSuspended()) {
            return [
                'state' => 'suspended',
                'is_suspended' => true,
                'live' => false,
                'stale' => false,
                'unreachable' => false,
                'cpu_absolute' => null,
                'memory_bytes' => null,
                'last_seen_at' => null,
            ];
        }

        $stats = $this->fetchLiveStats($server);
        if ($stats !== null) {
            return [
                'state' => Arr::get($stats, 'state', 'offline'),
                'is_suspended' => (bool) Arr::get($stats, 'is_suspended', false),
                'live' => true,
                'stale' => false,
                'unreachable' => false,
                'cpu_absolute' => (float) Arr::get($stats, 'utilization.cpu_absolute', 0),
                'memory_bytes' => (int) Arr::get($stats, 'utilization.memory_bytes', 0),
                'last_seen_at' => Carbon::now()->toAtomString(),
            ];
        }

        return $this->fallbackStatus($server);
    }

    /**
     * Returns the raw Wings `getDetails()` payload for a server, or null if it could not be
     * fetched (and could not be fetched recently either — see class docblock). Shares its cache
     * key with ResourceUtilizationController by design.
     *
     * @return array<string, mixed>|null
     */
    private function fetchLiveStats(Server $server): ?array
    {
        $downKey = "resources:down:$server->uuid";
        if ($this->cache->has($downKey)) {
            return null;
        }

        $liveKey = "resources:$server->uuid";
        $cached = $this->cache->get($liveKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $stats = $this->daemonServerRepository->setServer($server)->getDetails();
            $this->cache->put($liveKey, $stats, Carbon::now()->addSeconds(self::LIVE_CACHE_SECONDS));

            return $stats;
        } catch (DaemonConnectionException) {
            $this->cache->put($downKey, true, Carbon::now()->addSeconds(self::DOWN_CACHE_SECONDS));

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackStatus(Server $server): array
    {
        /** @var ServerResourceSample|null $sample */
        $sample = ServerResourceSample::query()
            ->where('server_id', $server->id)
            ->orderByDesc('recorded_at')
            ->first();

        $isRecent = $sample !== null
            && $sample->recorded_at->gt(Carbon::now()->subMinutes(self::STALE_SAMPLE_MAX_AGE_MINUTES));

        if ($sample !== null && $isRecent) {
            return [
                'state' => $sample->state,
                'is_suspended' => false,
                'live' => false,
                'stale' => true,
                'unreachable' => false,
                'cpu_absolute' => $sample->cpu_absolute,
                'memory_bytes' => $sample->memory_bytes,
                'last_seen_at' => $sample->recorded_at->toAtomString(),
            ];
        }

        return [
            'state' => null,
            'is_suspended' => false,
            'live' => false,
            'stale' => false,
            'unreachable' => true,
            'cpu_absolute' => null,
            'memory_bytes' => null,
            'last_seen_at' => $sample?->recorded_at?->toAtomString(),
        ];
    }

    private function pivotAttribute(Server $server, string $key): mixed
    {
        return $server->getAttribute('pivot')?->getAttribute($key);
    }
}
