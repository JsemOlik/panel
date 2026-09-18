<?php

namespace Pterodactyl\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * \Pterodactyl\Models\ServerResourceSample.
 *
 * A single point-in-time resource reading for a server, collected by polling Wings on a
 * schedule (see App\Console\Commands\Maintenance\CollectResourceStatsCommand). One row is
 * written per server per collection run when the daemon responds; a missed poll (offline
 * node, suspended/installing server, timeout) simply writes no row for that server for that
 * minute, which readers must render as a gap rather than interpolate through.
 *
 * network_rx_bytes / network_tx_bytes are the raw cumulative counters exactly as Wings
 * reports them (they reset to 0 when a container restarts) — deltas are computed by readers,
 * not at write time.
 *
 * @property int $id
 * @property int $server_id
 * @property Carbon $recorded_at
 * @property float $cpu_absolute
 * @property int $memory_bytes
 * @property int $disk_bytes
 * @property int $network_rx_bytes
 * @property int $network_tx_bytes
 * @property string $state
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Server $server
 */
class ServerResourceSample extends Model
{
    use MassPrunable;

    public const RESOURCE_NAME = 'resource_sample';

    protected $table = 'server_resource_samples';

    protected $fillable = [
        'server_id',
        'recorded_at',
        'cpu_absolute',
        'memory_bytes',
        'disk_bytes',
        'network_rx_bytes',
        'network_tx_bytes',
        'state',
    ];

    protected $casts = [
        'server_id' => 'integer',
        'recorded_at' => 'datetime',
        'cpu_absolute' => 'float',
        'memory_bytes' => 'integer',
        'disk_bytes' => 'integer',
        'network_rx_bytes' => 'integer',
        'network_tx_bytes' => 'integer',
    ];

    public static array $validationRules = [
        'server_id' => ['required', 'integer'],
        'recorded_at' => ['required', 'date'],
        'cpu_absolute' => ['required', 'numeric'],
        'memory_bytes' => ['required', 'integer'],
        'disk_bytes' => ['required', 'integer'],
        'network_rx_bytes' => ['required', 'integer'],
        'network_tx_bytes' => ['required', 'integer'],
        'state' => ['required', 'string', 'max:16'],
    ];

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Returns models to be pruned. Raw samples are kept only long enough to serve the
     * "hour"/"day" history ranges plus slack for the rollup job to safely backfill.
     *
     * @see https://laravel.com/docs/10.x/eloquent#pruning-models
     */
    public function prunable(): Builder
    {
        return static::where('recorded_at', '<=', Carbon::now()->subDays(config('resource-history.raw_retention_days', 3)));
    }
}
