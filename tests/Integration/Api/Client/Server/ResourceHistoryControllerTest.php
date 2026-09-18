<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Server;

use Carbon\Carbon;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\ServerResourceSample;
use Pterodactyl\Models\ServerResourceStatRollup;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

/**
 * Locks the resources/history endpoint to the shape a chart consuming it needs: an ordered,
 * gap-preserving list of points with pre-computed network deltas.
 */
class ResourceHistoryControllerTest extends ClientApiIntegrationTestCase
{
    public function tearDown(): void
    {
        ServerResourceStatRollup::query()->delete();
        ServerResourceSample::query()->delete();

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

    public function testHourRangeReturnsRawPointsWithComputedNetworkDeltas(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_WEBSOCKET_CONNECT]);

        $this->sample($server, Carbon::now()->subMinutes(3), ['cpu_absolute' => 5, 'network_rx_bytes' => 1000, 'network_tx_bytes' => 500]);
        $this->sample($server, Carbon::now()->subMinutes(2), ['cpu_absolute' => 15, 'network_rx_bytes' => 1800, 'network_tx_bytes' => 900]);

        $json = $this->actingAs($user)
            ->getJson("/api/client/servers/$server->uuid/resources/history?range=hour")
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('data', $json);
        $this->assertIsList($json['data']);
        $this->assertCount(2, $json['data']);

        $first = $json['data'][0]['attributes'];
        $second = $json['data'][1]['attributes'];

        foreach (['timestamp', 'state', 'cpu', 'memory_bytes', 'disk_bytes', 'network_rx_bytes', 'network_tx_bytes'] as $key) {
            $this->assertArrayHasKey($key, $first, "resources/history points must carry a `$key` field.");
        }

        // First point in the range has no prior sample to diff against.
        $this->assertSame(0, $first['network_rx_bytes']);
        $this->assertSame(0, $first['network_tx_bytes']);
        $this->assertSame('running', $first['state']);
        $this->assertEqualsWithDelta(5.0, $first['cpu'], 0.001);

        // Second point is the delta versus the first, not a raw cumulative counter.
        $this->assertSame(800, $second['network_rx_bytes']);
        $this->assertSame(400, $second['network_tx_bytes']);

        // Oldest first.
        $this->assertTrue(strtotime($first['timestamp']) <= strtotime($second['timestamp']));
    }

    public function testWeekRangeReturnsRollupPointsWithNullState(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_WEBSOCKET_CONNECT]);

        ServerResourceStatRollup::query()->create([
            'server_id' => $server->id,
            'bucket_start' => Carbon::now()->subDays(1)->startOfHour(),
            'cpu_avg' => 33.3,
            'cpu_max' => 50,
            'memory_avg_bytes' => 100,
            'memory_max_bytes' => 200,
            'disk_avg_bytes' => 300,
            'network_rx_bytes' => 400,
            'network_tx_bytes' => 500,
            'sample_count' => 12,
        ]);

        $json = $this->actingAs($user)
            ->getJson("/api/client/servers/$server->uuid/resources/history?range=week")
            ->assertOk()
            ->json();

        $this->assertCount(1, $json['data']);
        $point = $json['data'][0]['attributes'];

        $this->assertNull($point['state']);
        $this->assertEqualsWithDelta(33.3, $point['cpu'], 0.001);
        $this->assertSame(400, $point['network_rx_bytes']);
        $this->assertSame(500, $point['network_tx_bytes']);
    }

    public function testInvalidRangeIsRejected(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_WEBSOCKET_CONNECT]);

        $this->actingAs($user)
            ->getJson("/api/client/servers/$server->uuid/resources/history?range=decade")
            ->assertStatus(422);
    }

    public function testSubuserWithoutWebsocketConnectPermissionIsForbidden(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_FILE_READ]);

        $this->actingAs($user)
            ->getJson("/api/client/servers/$server->uuid/resources/history")
            ->assertForbidden();
    }

    public function testOwnerCanAlwaysReadHistoryWithNoExplicitPermissions(): void
    {
        $server = $this->createServerModel();

        $json = $this->actingAs($server->user)
            ->getJson("/api/client/servers/$server->uuid/resources/history")
            ->assertOk()
            ->json();

        $this->assertSame([], $json['data']);
    }
}
