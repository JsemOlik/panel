<?php

namespace Pterodactyl\Tests\Integration\Console\Commands\Maintenance;

use Mockery;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerResourceSample;
use GuzzleHttp\Exception\TransferException;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Exercises the resource-stats collector against a mocked daemon repository — no real Wings
 * is ever contacted in this suite.
 */
class CollectResourceStatsCommandTest extends IntegrationTestCase
{
    private Mockery\MockInterface $repository;

    /** @var array<int, Server> keyed by spl_object_id of the mock at call time */
    private array $servers = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(DaemonServerRepository::class);
        $this->app->instance(DaemonServerRepository::class, $this->repository);

        $this->repository->allows('setServer')->andReturnUsing(function (Server $server) {
            $this->servers[spl_object_id($this->repository)] = $server;

            return $this->repository;
        });
    }

    public function tearDown(): void
    {
        Mockery::close();

        // The command under test iterates over every Server in the database, so a previous
        // test's leftover servers would silently get polled here too (and trip the mock's
        // strict expectations) unless we clean them up after each test. We only touch the
        // Server row itself (not its Node/Location/User) to avoid disturbing fixtures other
        // test classes running in the same suite may depend on.
        ServerResourceSample::query()->delete();
        Server::query()->forceDelete();

        parent::tearDown();
    }

    public function testWritesOneSampleForARunningServer(): void
    {
        $server = $this->createServerModel();

        $this->repository->allows('getDetails')->andReturn([
            'state' => 'running',
            'is_suspended' => false,
            'utilization' => [
                'memory_bytes' => 512 * 1024 * 1024,
                'cpu_absolute' => 42.5,
                'disk_bytes' => 1024 * 1024 * 1024,
                'network' => ['rx_bytes' => 1000, 'tx_bytes' => 2000],
            ],
        ]);

        $this->assertSame(0, \Artisan::call('p:maintenance:collect-resource-stats'));

        $this->assertSame(1, ServerResourceSample::query()->where('server_id', $server->id)->count());

        $sample = ServerResourceSample::query()->where('server_id', $server->id)->first();
        $this->assertSame('running', $sample->state);
        $this->assertEqualsWithDelta(42.5, $sample->cpu_absolute, 0.001);
        $this->assertSame(512 * 1024 * 1024, $sample->memory_bytes);
        $this->assertSame(1024 * 1024 * 1024, $sample->disk_bytes);
        // Raw table stores the cumulative counters exactly as Wings reported them.
        $this->assertSame(1000, $sample->network_rx_bytes);
        $this->assertSame(2000, $sample->network_tx_bytes);
    }

    public function testDoesNotContactTheDaemonForASuspendedServer(): void
    {
        $server = $this->createServerModel();
        $server->update(['status' => Server::STATUS_SUSPENDED]);

        $this->repository->shouldNotReceive('setServer');

        $this->assertSame(0, \Artisan::call('p:maintenance:collect-resource-stats'));

        $this->assertSame(0, ServerResourceSample::query()->where('server_id', $server->id)->count());
    }

    public function testDoesNotContactTheDaemonForAnInstallingServer(): void
    {
        $server = $this->createServerModel(['status' => Server::STATUS_INSTALLING]);

        $this->repository->shouldNotReceive('setServer');

        $this->assertSame(0, \Artisan::call('p:maintenance:collect-resource-stats'));

        $this->assertSame(0, ServerResourceSample::query()->where('server_id', $server->id)->count());
    }

    public function testSkipsServerWithoutWritingAGarbageRowWhenTheDaemonIsUnreachable(): void
    {
        $server = $this->createServerModel();

        $this->repository->allows('getDetails')->andThrow(
            new DaemonConnectionException(new TransferException('connection refused'))
        );

        // A single unreachable server must not fail the whole run.
        $this->assertSame(0, \Artisan::call('p:maintenance:collect-resource-stats'));

        $this->assertSame(0, ServerResourceSample::query()->where('server_id', $server->id)->count());
    }

    public function testOneUnreachableServerDoesNotBlockOthersFromBeingCollected(): void
    {
        $reachable = $this->createServerModel(['name' => 'reachable']);
        $unreachable = $this->createServerModel(['name' => 'unreachable']);

        $this->repository->allows('getDetails')->andReturnUsing(function () use ($unreachable) {
            $current = $this->servers[spl_object_id($this->repository)];

            if ($current->id === $unreachable->id) {
                throw new DaemonConnectionException(new TransferException('timed out'));
            }

            return [
                'state' => 'running',
                'utilization' => [
                    'memory_bytes' => 100,
                    'cpu_absolute' => 1.0,
                    'disk_bytes' => 100,
                    'network' => ['rx_bytes' => 0, 'tx_bytes' => 0],
                ],
            ];
        });

        $this->assertSame(0, \Artisan::call('p:maintenance:collect-resource-stats'));

        $this->assertSame(1, ServerResourceSample::query()->where('server_id', $reachable->id)->count());
        $this->assertSame(0, ServerResourceSample::query()->where('server_id', $unreachable->id)->count());
    }
}
