<?php

namespace Pterodactyl\Console\Commands\Maintenance;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Console\Command;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\ServerResourceSample;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Polls Wings once for each collectable server and writes one resource sample row per
 * server that responds. This is intentionally a plain synchronous REST poll (reusing the
 * same DaemonServerRepository::getDetails() call the live "resources" endpoint already
 * makes) rather than a persistent websocket consumer — see the resource-history design
 * notes for the reasoning. At the current fleet size (~13 servers) this comfortably
 * finishes well within the scheduler's one-minute cadence.
 *
 * A server is skipped for this run (no row written — a gap, not a zeroed/interpolated
 * point) rather than failing the whole run when:
 *   - the server is suspended, still installing, or otherwise not in a normal state
 *     where Wings can be expected to answer meaningfully;
 *   - the server's node is in maintenance mode;
 *   - the Wings request throws a DaemonConnectionException (node unreachable, timeout,
 *     etc.) — logged as a warning and moved on, never allowed to abort the batch.
 */
class CollectResourceStatsCommand extends Command
{
    protected $signature = 'p:maintenance:collect-resource-stats';

    protected $description = 'Polls Wings for current resource usage on every collectable server and records one sample per server.';

    public function __construct(private DaemonServerRepository $repository)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $servers = Server::query()
            ->with('node')
            ->get()
            ->filter(fn (Server $server) => $this->isCollectable($server));

        $recordedAt = CarbonImmutable::now();
        $collected = 0;
        $skipped = 0;

        foreach ($servers as $server) {
            try {
                $details = $this->repository->setServer($server)->getDetails();
            } catch (DaemonConnectionException $exception) {
                $skipped++;
                Log::warning("Skipping resource sample for server {$server->uuid}: daemon unreachable.", [
                    'server_id' => $server->id,
                    'node_id' => $server->node_id,
                    'exception' => $exception->getMessage(),
                ]);

                continue;
            }

            ServerResourceSample::query()->create([
                'server_id' => $server->id,
                'recorded_at' => $recordedAt,
                'state' => Arr::get($details, 'state', 'unknown'),
                'cpu_absolute' => (float) Arr::get($details, 'utilization.cpu_absolute', 0),
                'memory_bytes' => (int) Arr::get($details, 'utilization.memory_bytes', 0),
                'disk_bytes' => (int) Arr::get($details, 'utilization.disk_bytes', 0),
                'network_rx_bytes' => (int) Arr::get($details, 'utilization.network.rx_bytes', 0),
                'network_tx_bytes' => (int) Arr::get($details, 'utilization.network.tx_bytes', 0),
            ]);

            $collected++;
        }

        $this->info("Collected $collected resource sample(s); skipped $skipped unreachable/unavailable server(s).");

        return static::SUCCESS;
    }

    /**
     * A server is worth polling only when it is installed, not suspended, and its node
     * isn't in maintenance — anything else is expected to fail or return meaningless data,
     * so we skip the daemon call entirely rather than logging noise for every poll.
     */
    private function isCollectable(Server $server): bool
    {
        if (!$server->isInstalled() || $server->isSuspended()) {
            return false;
        }

        return !$server->node->isUnderMaintenance();
    }
}
