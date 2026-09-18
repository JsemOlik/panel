<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Areas;

use Carbon\Carbon;
use Ramsey\Uuid\Uuid;
use Pterodactyl\Models\Area;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerResourceSample;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

/**
 * Locks the wallboard endpoint to the shape resources/scripts/components/wallboard reads, and
 * pins the "degrade visibly, never silently" contract: a server whose live Wings call fails must
 * never come back looking identical to one that is actually online.
 */
class AreaWallboardControllerTest extends ClientApiIntegrationTestCase
{
    protected function tearDown(): void
    {
        ServerResourceSample::query()->delete();
        Area::query()->delete();

        parent::tearDown();
    }

    private function createArea(array $attributes = []): Area
    {
        return Area::query()->create($attributes + [
            'uuid' => Uuid::uuid4()->toString(),
            'name' => 'Test Area',
        ]);
    }

    private function mockDaemon(): \Mockery\MockInterface
    {
        $service = \Mockery::mock(DaemonServerRepository::class);
        $this->app->instance(DaemonServerRepository::class, $service);

        return $service;
    }

    public function testWallboardOnlyReturnsAreasTheUserCanView(): void
    {
        $service = $this->mockDaemon();
        $service->shouldReceive('setServer')->andReturnSelf();
        $service->shouldReceive('getDetails')->andReturn([
            'state' => 'running',
            'is_suspended' => false,
            'utilization' => ['cpu_absolute' => 12.5, 'memory_bytes' => 512],
        ]);

        $user = User::factory()->create();
        $visibleServer = $this->createServerModel(['owner_id' => $user->id]);
        $hiddenServer = $this->createServerModel();

        $visibleArea = $this->createArea(['name' => 'Visible Area']);
        $visibleArea->servers()->attach($visibleServer->id, ['role' => Area::ROLE_MEMBER, 'sort_order' => 0]);

        $hiddenArea = $this->createArea(['name' => 'Hidden Area']);
        $hiddenArea->servers()->attach($hiddenServer->id, ['role' => Area::ROLE_MEMBER, 'sort_order' => 0]);

        $json = $this->actingAs($user)
            ->getJson('/api/client/areas/wallboard')
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('attributes', $json);
        $this->assertArrayHasKey('generated_at', $json['attributes']);
        $this->assertArrayHasKey('areas', $json['attributes']);
        $this->assertIsList($json['attributes']['areas']);

        $names = array_column($json['attributes']['areas'], 'name');
        $this->assertContains('Visible Area', $names);
        $this->assertNotContains('Hidden Area', $names);

        $area = $json['attributes']['areas'][array_search('Visible Area', $names, true)];
        $this->assertCount(1, $area['members']);

        $member = $area['members'][0];
        foreach (['id', 'uuid', 'name', 'role', 'state', 'is_suspended', 'live', 'stale', 'unreachable', 'cpu_absolute', 'memory_bytes', 'last_seen_at', 'is_node_under_maintenance'] as $key) {
            $this->assertArrayHasKey($key, $member, "wallboard member payload must carry `$key`.");
        }

        $this->assertSame('running', $member['state']);
        $this->assertTrue($member['live']);
        $this->assertFalse($member['stale']);
        $this->assertFalse($member['unreachable']);
    }

    public function testUnreachableServerFallsBackToRecentSampleAndIsMarkedStale(): void
    {
        $service = $this->mockDaemon();
        $service->shouldReceive('setServer')->andReturnSelf();
        $service->shouldReceive('getDetails')->andThrow(
            new DaemonConnectionException(new \GuzzleHttp\Exception\TransferException('connection refused'), false)
        );

        $user = User::factory()->create();
        $server = $this->createServerModel(['owner_id' => $user->id]);

        ServerResourceSample::query()->create([
            'server_id' => $server->id,
            'recorded_at' => Carbon::now()->subMinutes(2),
            'state' => 'running',
            'cpu_absolute' => 7.5,
            'memory_bytes' => 256,
            'disk_bytes' => 100,
            'network_rx_bytes' => 0,
            'network_tx_bytes' => 0,
        ]);

        $area = $this->createArea();
        $area->servers()->attach($server->id, ['role' => Area::ROLE_MEMBER, 'sort_order' => 0]);

        $json = $this->actingAs($user)
            ->getJson('/api/client/areas/wallboard')
            ->assertOk()
            ->json();

        $member = $json['attributes']['areas'][0]['members'][0];

        $this->assertFalse($member['live'], 'a failed daemon call must never be reported as live.');
        $this->assertTrue($member['stale'], 'a fallback-to-sample response must be marked stale so the frontend renders it distinctly.');
        $this->assertFalse($member['unreachable']);
        $this->assertSame('running', $member['state']);
        $this->assertNotNull($member['last_seen_at']);
    }

    public function testUnreachableServerWithNoRecentSampleIsMarkedUnreachableNotOnline(): void
    {
        $service = $this->mockDaemon();
        $service->shouldReceive('setServer')->andReturnSelf();
        $service->shouldReceive('getDetails')->andThrow(
            new DaemonConnectionException(new \GuzzleHttp\Exception\TransferException('connection refused'), false)
        );

        $user = User::factory()->create();
        $server = $this->createServerModel(['owner_id' => $user->id]);

        // A stale sample from well outside the "recent" window must not be treated as current.
        ServerResourceSample::query()->create([
            'server_id' => $server->id,
            'recorded_at' => Carbon::now()->subHours(2),
            'state' => 'running',
            'cpu_absolute' => 7.5,
            'memory_bytes' => 256,
            'disk_bytes' => 100,
            'network_rx_bytes' => 0,
            'network_tx_bytes' => 0,
        ]);

        $area = $this->createArea();
        $area->servers()->attach($server->id, ['role' => Area::ROLE_MEMBER, 'sort_order' => 0]);

        $json = $this->actingAs($user)
            ->getJson('/api/client/areas/wallboard')
            ->assertOk()
            ->json();

        $member = $json['attributes']['areas'][0]['members'][0];

        $this->assertFalse($member['live']);
        $this->assertFalse($member['stale']);
        $this->assertTrue($member['unreachable'], 'a server with no live data and no recent sample must be reported unreachable, never as an online/offline state.');
        $this->assertNull($member['state']);
    }

    public function testSuspendedServerIsReportedWithoutContactingTheDaemon(): void
    {
        $service = $this->mockDaemon();
        $service->shouldNotReceive('setServer');
        $service->shouldNotReceive('getDetails');

        $user = User::factory()->create();
        $server = $this->createServerModel(['owner_id' => $user->id, 'status' => Server::STATUS_SUSPENDED]);

        $area = $this->createArea();
        $area->servers()->attach($server->id, ['role' => Area::ROLE_MEMBER, 'sort_order' => 0]);

        $json = $this->actingAs($user)
            ->getJson('/api/client/areas/wallboard')
            ->assertOk()
            ->json();

        $member = $json['attributes']['areas'][0]['members'][0];

        $this->assertSame('suspended', $member['state']);
        $this->assertTrue($member['is_suspended']);
    }
}
