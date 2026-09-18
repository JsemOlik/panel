<?php

namespace Pterodactyl\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * \Pterodactyl\Models\ServerResourceStatRollup.
 *
 * An hourly aggregate of ServerResourceSample rows, built by
 * App\Console\Commands\Maintenance\RollupResourceHistoryCommand once an hour of raw samples
 * is fully closed. Long-retention companion to the short-lived raw samples table; this is
 * what serves the "week"/"month" history ranges. An hour with zero raw samples (node was
 * unreachable, server was offline the whole hour) simply has no row here — never a
 * zero-filled or interpolated one.
 *
 * network_rx_bytes / network_tx_bytes here are already deltas (throughput across the
 * bucket), unlike the raw table's cumulative counters, since that's what a chart wants to
 * plot directly.
 *
 * @property int $id
 * @property int $server_id
 * @property Carbon $bucket_start
 * @property float $cpu_avg
 * @property float $cpu_max
 * @property int $memory_avg_bytes
 * @property int $memory_max_bytes
 * @property int $disk_avg_bytes
 * @property int $network_rx_bytes
 * @property int $network_tx_bytes
 * @property int $sample_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Server $server
 */
class ServerResourceStatRollup extends Model
{
    use MassPrunable;

    public const RESOURCE_NAME = 'resource_stat_rollup';

    protected $table = 'server_resource_stat_rollups';

    protected $fillable = [
        'server_id',
        'bucket_start',
        'cpu_avg',
        'cpu_max',
        'memory_avg_bytes',
        'memory_max_bytes',
        'disk_avg_bytes',
        'network_rx_bytes',
        'network_tx_bytes',
        'sample_count',
    ];

    protected $casts = [
        'server_id' => 'integer',
        'bucket_start' => 'datetime',
        'cpu_avg' => 'float',
        'cpu_max' => 'float',
        'memory_avg_bytes' => 'integer',
        'memory_max_bytes' => 'integer',
        'disk_avg_bytes' => 'integer',
        'network_rx_bytes' => 'integer',
        'network_tx_bytes' => 'integer',
        'sample_count' => 'integer',
    ];

    public static array $validationRules = [
        'server_id' => ['required', 'integer'],
        'bucket_start' => ['required', 'date'],
        'cpu_avg' => ['required', 'numeric'],
        'cpu_max' => ['required', 'numeric'],
        'memory_avg_bytes' => ['required', 'integer'],
        'memory_max_bytes' => ['required', 'integer'],
        'disk_avg_bytes' => ['required', 'integer'],
        'network_rx_bytes' => ['required', 'integer'],
        'network_tx_bytes' => ['required', 'integer'],
        'sample_count' => ['required', 'integer'],
    ];

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Returns models to be pruned.
     *
     * @see https://laravel.com/docs/10.x/eloquent#pruning-models
     */
    public function prunable(): Builder
    {
        return static::where('bucket_start', '<=', Carbon::now()->subDays(config('resource-history.rollup_retention_days', 365)));
    }
}
