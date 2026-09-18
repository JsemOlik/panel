<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Pterodactyl\Models\Server;
use Illuminate\Cache\Repository;
use Pterodactyl\Models\Permission;
use Illuminate\Validation\ValidationException;
use Pterodactyl\Models\ServerResourceSample;
use Pterodactyl\Models\ServerResourceStatRollup;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\GetServerRequest;
use Pterodactyl\Transformers\Api\Client\ResourceHistoryTransformer;

/**
 * Serves historical resource usage points for a server's CPU/memory/disk/network charts.
 *
 * Storage/read contract (for anything consuming this data, e.g. a fleet-wide wallboard):
 *   - GET /api/client/servers/{server}/resources/history?range=hour|day|week|month
 *   - `hour`/`day` are served from the raw `server_resource_samples` table (one row per
 *     server per minute the collector successfully polled Wings); `week`/`month` are served
 *     from the hourly `server_resource_stat_rollups` table.
 *   - Response is a standard Fractal collection: `{ data: [{ object, attributes }, ...] }`,
 *     each `attributes` shaped by ResourceHistoryTransformer as
 *     `{ timestamp: ISO8601 string, state: string|null, cpu: float (percent, can exceed
 *     100 on multi-core limits), memory_bytes: int, disk_bytes: int, network_rx_bytes: int,
 *     network_tx_bytes: int }`.
 *   - `network_*_bytes` is ALWAYS a per-interval delta (bytes transferred since the previous
 *     point), never a cumulative counter, regardless of range — callers should not attempt
 *     their own diffing. `state` is only present for raw-range points (hour/day); it is
 *     null for rollup-range points (week/month), since a hive of Wings states isn't
 *     meaningfully summarizable to one value across a full hour.
 *   - A missing point (a minute the collector skipped, or an hour with zero samples) is
 *     simply absent from the array — the response is never densely gap-filled. Chart code
 *     reading this must not linearly interpolate across a gap of more than one expected
 *     interval; render it as a break in the line instead.
 *   - Points are always ordered oldest to newest.
 *   - Response is cached for 30s per (server, range) to absorb multiple staff loading the
 *     same server's chart around the same time.
 */
class ResourceHistoryController extends ClientApiController
{
    private const RANGES = ['hour', 'day', 'week', 'month'];

    public function __construct(private Repository $cache)
    {
        parent::__construct();
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(GetServerRequest $request, Server $server): array
    {
        $this->authorize(Permission::ACTION_WEBSOCKET_CONNECT, $server);

        $range = $request->query('range', 'day');
        if (!in_array($range, self::RANGES, true)) {
            throw ValidationException::withMessages([
                'range' => 'The range must be one of: ' . implode(', ', self::RANGES) . '.',
            ]);
        }

        $key = "resources:history:$server->uuid:$range";
        $points = $this->cache->remember($key, Carbon::now()->addSeconds(30), function () use ($server, $range) {
            return $range === 'hour' || $range === 'day'
                ? $this->rawPoints($server, $range === 'hour' ? 1 : 24)
                : $this->rollupPoints($server, $range === 'week' ? 7 : 31);
        });

        return $this->fractal->collection($points)
            ->transformWith($this->getTransformer(ResourceHistoryTransformer::class))
            ->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rawPoints(Server $server, int $hours): array
    {
        $samples = ServerResourceSample::query()
            ->where('server_id', $server->id)
            ->where('recorded_at', '>=', Carbon::now()->subHours($hours))
            ->orderBy('recorded_at')
            ->get();

        $points = [];
        $previous = null;

        /** @var ServerResourceSample $sample */
        foreach ($samples as $sample) {
            $points[] = [
                'timestamp' => $sample->recorded_at->toAtomString(),
                'state' => $sample->state,
                'cpu' => $sample->cpu_absolute,
                'memory_bytes' => $sample->memory_bytes,
                'disk_bytes' => $sample->disk_bytes,
                'network_rx_bytes' => $previous === null
                    ? 0
                    : max(0, $sample->network_rx_bytes - $previous->network_rx_bytes),
                'network_tx_bytes' => $previous === null
                    ? 0
                    : max(0, $sample->network_tx_bytes - $previous->network_tx_bytes),
            ];

            $previous = $sample;
        }

        return $points;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rollupPoints(Server $server, int $days): array
    {
        $rollups = ServerResourceStatRollup::query()
            ->where('server_id', $server->id)
            ->where('bucket_start', '>=', Carbon::now()->subDays($days))
            ->orderBy('bucket_start')
            ->get();

        return Collection::make($rollups)->map(fn (ServerResourceStatRollup $rollup) => [
            'timestamp' => $rollup->bucket_start->toAtomString(),
            'state' => null,
            'cpu' => $rollup->cpu_avg,
            'memory_bytes' => $rollup->memory_avg_bytes,
            'disk_bytes' => $rollup->disk_avg_bytes,
            'network_rx_bytes' => $rollup->network_rx_bytes,
            'network_tx_bytes' => $rollup->network_tx_bytes,
        ])->all();
    }
}
