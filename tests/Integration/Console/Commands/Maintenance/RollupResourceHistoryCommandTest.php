<?php

namespace Pterodactyl\Tests\Integration\Console\Commands\Maintenance;

use Carbon\Carbon;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerResourceSample;
use Pterodactyl\Models\ServerResourceStatRollup;
use Pterodactyl\Tests\Integration\IntegrationTestCase;

class RollupResourceHistoryCommandTest extends IntegrationTestCase
{
    public function tearDown(): void
    {
        // A few assertions here count ALL rollup rows, so leftover servers/samples from a
        // previous test must not carry over. We only touch the Server row itself (not its
        // Node/Location/User) to avoid disturbing fixtures other test classes running in the
        // same suite may depend on.
        ServerResourceStatRollup::query()->delete();
        ServerResourceSample::query()->delete();
        Server::query()->forceDelete();

        parent::tearDown();
    }

    private function sample(Server $server, Carbon $recordedAt, array $attributes = []): ServerResourceSample
    {
        return ServerResourceSample::query()->create($attributes + [
            'server_id' => $server->id,
            'recorded_at' => $recordedAt,
            'state' => 'running',
            'cpu_absolute' => 10,
            'memory_bytes' => 100,
            'disk_bytes' => 100,
            'network_rx_bytes' => 0,
            'network_tx_bytes' => 0,
        ]);
    }

    public function testClosedHourWithSamplesIsAggregatedIntoARollup(): void
    {
        $server = $this->createServerModel();

        $bucketStart = Carbon::now('UTC')->subHours(2)->startOfHour();
        $this->sample($server, $bucketStart->copy()->addMinutes(1), ['cpu_absolute' => 10, 'memory_bytes' => 100, 'network_rx_bytes' => 1000, 'network_tx_bytes' => 2000]);
        $this->sample($server, $bucketStart->copy()->addMinutes(30), ['cpu_absolute' => 50, 'memory_bytes' => 300, 'network_rx_bytes' => 1500, 'network_tx_bytes' => 2500]);
        $this->sample($server, $bucketStart->copy()->addMinutes(59), ['cpu_absolute' => 20, 'memory_bytes' => 200, 'network_rx_bytes' => 2000, 'network_tx_bytes' => 3000]);

        $this->assertSame(0, \Artisan::call('p:maintenance:rollup-resource-history'));

        $rollup = ServerResourceStatRollup::query()->where('server_id', $server->id)->where('bucket_start', $bucketStart)->first();

        $this->assertNotNull($rollup, 'A rollup row should be created for the fully-closed hour.');
        $this->assertSame(3, $rollup->sample_count);
        $this->assertEqualsWithDelta((10 + 50 + 20) / 3, $rollup->cpu_avg, 0.001);
        $this->assertEqualsWithDelta(50, $rollup->cpu_max, 0.001);
        $this->assertSame(200, $rollup->memory_avg_bytes);
        $this->assertSame(300, $rollup->memory_max_bytes);
        // Delta across the bucket (max - min), not the raw cumulative counters.
        $this->assertSame(1000, $rollup->network_rx_bytes);
        $this->assertSame(1000, $rollup->network_tx_bytes);
    }

    public function testCurrentlyOpenHourIsNeverRolledUp(): void
    {
        $server = $this->createServerModel();

        $this->sample($server, Carbon::now('UTC'));

        $this->assertSame(0, \Artisan::call('p:maintenance:rollup-resource-history'));

        $this->assertSame(0, ServerResourceStatRollup::query()->where('server_id', $server->id)->count());
    }

    public function testHourWithNoSamplesProducesNoRollupRow(): void
    {
        $server = $this->createServerModel();

        // Only the daemon-unreachable case is exercised here: no raw samples at all for a
        // closed hour, e.g. the node was down. Nothing else primes the rollup table.
        $this->assertSame(0, \Artisan::call('p:maintenance:rollup-resource-history'));

        $this->assertSame(0, ServerResourceStatRollup::query()->count());
    }

    public function testRunningTwiceIsIdempotentAndDoesNotDuplicateRows(): void
    {
        $server = $this->createServerModel();

        $bucketStart = Carbon::now('UTC')->subHours(3)->startOfHour();
        $this->sample($server, $bucketStart->copy()->addMinutes(5));

        $this->assertSame(0, \Artisan::call('p:maintenance:rollup-resource-history'));
        $this->assertSame(0, \Artisan::call('p:maintenance:rollup-resource-history'));

        $this->assertSame(
            1,
            ServerResourceStatRollup::query()->where('server_id', $server->id)->where('bucket_start', $bucketStart)->count()
        );
    }

    public function testGapBetweenTwoRolledUpHoursIsNotBackfilled(): void
    {
        $server = $this->createServerModel();

        $firstHour = Carbon::now('UTC')->subHours(5)->startOfHour();
        $thirdHour = Carbon::now('UTC')->subHours(3)->startOfHour();

        $this->sample($server, $firstHour->copy()->addMinutes(1));
        // The hour in between (subHours(4)) has no samples at all — the node was down.
        $this->sample($server, $thirdHour->copy()->addMinutes(1));

        $this->assertSame(0, \Artisan::call('p:maintenance:rollup-resource-history'));

        $this->assertSame(2, ServerResourceStatRollup::query()->where('server_id', $server->id)->count());
        $this->assertSame(
            0,
            ServerResourceStatRollup::query()->where('server_id', $server->id)->where('bucket_start', $firstHour->copy()->addHour())->count(),
            'The empty hour between two populated ones must stay a gap, not an interpolated row.'
        );
    }
}
