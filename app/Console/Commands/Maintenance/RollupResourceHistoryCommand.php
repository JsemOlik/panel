<?php

namespace Pterodactyl\Console\Commands\Maintenance;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates raw ServerResourceSample rows into hourly ServerResourceStatRollup buckets.
 *
 * Runs hourly, but is written to be safe to run more often or to catch up after downtime:
 * it finds every (server_id, hour) pair that has raw samples and no rollup row yet, and
 * only rolls up buckets that are fully closed (the current, still-open hour is always left
 * alone). Each bucket is written with an upsert keyed on (server_id, bucket_start), so a
 * re-run of an already-rolled-up bucket is a no-op rather than double-counting.
 *
 * An hour with zero raw samples for a server (node unreachable, server offline) produces no
 * rollup row at all — never a zero-filled row that would misread as "idle".
 *
 * Network counters are cumulative in the raw table; here they're converted into a per-bucket
 * delta (max - min observed value within the bucket, floored at 0 to absorb a mid-bucket
 * container restart resetting the counter). This is an approximation when a counter both
 * resets and grows past its pre-reset value within the same bucket — acceptable at an
 * hourly granularity, called out as a known limitation.
 */
class RollupResourceHistoryCommand extends Command
{
    protected $signature = 'p:maintenance:rollup-resource-history';

    protected $description = 'Aggregates raw resource samples into hourly rollups for long-term retention.';

    public function handle(): int
    {
        $currentHourStart = Carbon::now('UTC')->startOfHour();

        $pendingBuckets = DB::table('server_resource_samples')
            ->selectRaw("server_id, DATE_FORMAT(recorded_at, '%Y-%m-%d %H:00:00') as bucket_start")
            ->where('recorded_at', '<', $currentHourStart)
            ->whereNotExists(function ($query) {
                $query->selectRaw(1)
                    ->from('server_resource_stat_rollups')
                    ->whereColumn('server_resource_stat_rollups.server_id', 'server_resource_samples.server_id')
                    ->whereRaw("server_resource_stat_rollups.bucket_start = DATE_FORMAT(server_resource_samples.recorded_at, '%Y-%m-%d %H:00:00')");
            })
            ->groupBy('server_id', 'bucket_start')
            ->get();

        $rolled = 0;

        foreach ($pendingBuckets as $bucket) {
            $bucketStart = Carbon::parse($bucket->bucket_start, 'UTC');
            $bucketEnd = $bucketStart->copy()->addHour();

            $aggregate = DB::table('server_resource_samples')
                ->where('server_id', $bucket->server_id)
                ->where('recorded_at', '>=', $bucketStart)
                ->where('recorded_at', '<', $bucketEnd)
                ->selectRaw('
                    AVG(cpu_absolute) as cpu_avg,
                    MAX(cpu_absolute) as cpu_max,
                    AVG(memory_bytes) as memory_avg_bytes,
                    MAX(memory_bytes) as memory_max_bytes,
                    AVG(disk_bytes) as disk_avg_bytes,
                    MAX(network_rx_bytes) as network_rx_max,
                    MIN(network_rx_bytes) as network_rx_min,
                    MAX(network_tx_bytes) as network_tx_max,
                    MIN(network_tx_bytes) as network_tx_min,
                    COUNT(*) as sample_count
                ')
                ->first();

            if (!$aggregate || (int) $aggregate->sample_count === 0) {
                continue;
            }

            DB::table('server_resource_stat_rollups')->updateOrInsert(
                ['server_id' => $bucket->server_id, 'bucket_start' => $bucketStart],
                [
                    'cpu_avg' => (float) $aggregate->cpu_avg,
                    'cpu_max' => (float) $aggregate->cpu_max,
                    'memory_avg_bytes' => (int) round((float) $aggregate->memory_avg_bytes),
                    'memory_max_bytes' => (int) $aggregate->memory_max_bytes,
                    'disk_avg_bytes' => (int) round((float) $aggregate->disk_avg_bytes),
                    'network_rx_bytes' => max(0, (int) $aggregate->network_rx_max - (int) $aggregate->network_rx_min),
                    'network_tx_bytes' => max(0, (int) $aggregate->network_tx_max - (int) $aggregate->network_tx_min),
                    'sample_count' => (int) $aggregate->sample_count,
                    'updated_at' => Carbon::now(),
                    'created_at' => Carbon::now(),
                ]
            );

            $rolled++;
        }

        $this->info("Rolled up $rolled resource history bucket(s).");

        return static::SUCCESS;
    }
}
