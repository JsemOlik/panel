<?php

namespace Pterodactyl\Tests\Integration\Services\Areas;

use Mockery;
use Ramsey\Uuid\Uuid;
use Pterodactyl\Models\Area;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Subuser;
use Pterodactyl\Models\Permission;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\TransferException;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Pterodactyl\Services\Areas\AreaPowerActionService;
use Pterodactyl\Repositories\Wings\DaemonPowerRepository;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Exercises the sequencing and partial-failure branches of AreaPowerActionService against a
 * real, database-backed Area/Server/pivot graph, with the Wings-facing repositories mocked out
 * (no real daemon calls are made anywhere in this suite, and the poll timeouts are configured
 * down to 0 so a "never reaches the target state" branch fails on its first poll instead of
 * actually sleeping).
 */
class AreaPowerActionServiceTest extends IntegrationTestCase
{
    private Mockery\MockInterface $powerRepository;

    private Mockery\MockInterface $serverRepository;

    private AreaPowerActionService $service;

    /** @var array<int, Server> The server each mock is currently "set" to, keyed by spl_object_id of the mock. */
    private array $servers = [];

    public function setUp(): void
    {
        parent::setUp();

        config()->set('pterodactyl.areas.start_timeout', 0);
        config()->set('pterodactyl.areas.start_poll_interval', 1);
        config()->set('pterodactyl.areas.stop_timeout', 0);
        config()->set('pterodactyl.areas.stop_poll_interval', 1);

        $this->powerRepository = Mockery::mock(DaemonPowerRepository::class);
        $this->serverRepository = Mockery::mock(DaemonServerRepository::class);

        $this->app->instance(DaemonPowerRepository::class, $this->powerRepository);
        $this->app->instance(DaemonServerRepository::class, $this->serverRepository);

        $this->powerRepository->allows('setServer')->andReturnUsing(function (Server $server) {
            $this->servers[spl_object_id($this->powerRepository)] = $server;

            return $this->powerRepository;
        });
        $this->serverRepository->allows('setServer')->andReturnUsing(function (Server $server) {
            $this->servers[spl_object_id($this->serverRepository)] = $server;

            return $this->serverRepository;
        });

        $this->service = $this->app->make(AreaPowerActionService::class);
    }

    public function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function currentPowerServerName(): string
    {
        return $this->servers[spl_object_id($this->powerRepository)]->name ?? 'unknown';
    }

    /**
     * Builds an area with three members (attached in a deliberately scrambled order, so a test
     * that only happened to iterate in attach order wouldn't accidentally pass) plus a proxy.
     *
     * @return array{Area, Server, Server, Server, Server} [$area, $memberA, $memberB, $memberC, $proxy]
     */
    private function makeArea(): array
    {
        $area = Area::query()->create(['uuid' => Uuid::uuid4()->toString(), 'name' => 'Kids Lobby']);

        $memberA = $this->createServerModel(['name' => 'member-a']);
        $memberB = $this->createServerModel(['name' => 'member-b']);
        $memberC = $this->createServerModel(['name' => 'member-c']);
        $proxy = $this->createServerModel(['name' => 'proxy']);

        $area->servers()->attach($memberC->id, ['role' => Area::ROLE_MEMBER, 'sort_order' => 3]);
        $area->servers()->attach($memberA->id, ['role' => Area::ROLE_MEMBER, 'sort_order' => 1]);
        $area->servers()->attach($memberB->id, ['role' => Area::ROLE_MEMBER, 'sort_order' => 2]);
        $area->servers()->attach($proxy->id, ['role' => Area::ROLE_PROXY, 'sort_order' => 0]);

        return [$area->fresh(), $memberA, $memberB, $memberC, $proxy];
    }

    public function testStartsMembersInSortOrderThenProxy(): void
    {
        [$area] = $this->makeArea();

        $order = [];

        $this->powerRepository->allows('send')->with('start')->andReturnUsing(function () use (&$order) {
            $order[] = $this->currentPowerServerName();

            return new Response();
        });

        $this->serverRepository->allows('getDetails')->andReturn(['state' => 'running']);

        $result = $this->service->send($area, 'start');

        $this->assertSame(['member-a', 'member-b', 'member-c', 'proxy'], $order);
        $this->assertSame(['member-a', 'member-b', 'member-c', 'proxy'], $result['succeeded']);
        $this->assertSame([], $result['skipped']);
        $this->assertSame([], $result['failed']);
        $this->assertSame('start', $result['signal']);
    }

    public function testMemberTimingOutDuringStartDoesNotBlockOthersOrProxy(): void
    {
        [$area] = $this->makeArea();

        $this->powerRepository->allows('send')->with('start')->andReturn(new Response());

        $this->serverRepository->allows('getDetails')->andReturnUsing(function () {
            $current = $this->servers[spl_object_id($this->serverRepository)];

            // member-b never comes up; everyone else reports running immediately.
            return ['state' => $current->name === 'member-b' ? 'offline' : 'running'];
        });

        $result = $this->service->send($area, 'start');

        $this->assertSame(['member-a', 'member-c', 'proxy'], $result['succeeded']);
        $this->assertSame(['member-b'], $result['failed']);
        $this->assertSame([], $result['skipped']);
    }

    public function testProxyIsSkippedWhenNoMemberReachesRunning(): void
    {
        [$area] = $this->makeArea();

        $this->powerRepository->allows('send')->with('start')->andReturnUsing(function () {
            // The proxy must never even be sent a start signal in this scenario.
            $this->assertNotSame('proxy', $this->currentPowerServerName());

            return new Response();
        });

        $this->serverRepository->allows('getDetails')->andReturn(['state' => 'offline']);

        $result = $this->service->send($area, 'start');

        $this->assertSame([], $result['succeeded']);
        $this->assertSame(['member-a', 'member-b', 'member-c'], $result['failed']);
        $this->assertSame(['proxy'], $result['skipped']);
    }

    public function testStopSendsToProxyFirstAndProceedsToMembersEvenIfProxyFails(): void
    {
        [$area] = $this->makeArea();

        $order = [];

        $this->powerRepository->allows('send')->with('stop')->andReturnUsing(function () use (&$order) {
            $name = $this->currentPowerServerName();
            $order[] = $name;

            if ($name === 'proxy') {
                throw new DaemonConnectionException(new TransferException('unreachable'));
            }

            return new Response();
        });

        $result = $this->service->send($area, 'stop');

        // Proxy must be attempted before any member.
        $this->assertSame('proxy', $order[0]);
        $this->assertSame(['member-a', 'member-b', 'member-c'], array_slice($order, 1));

        $this->assertSame(['member-a', 'member-b', 'member-c'], $result['succeeded']);
        $this->assertSame(['proxy'], $result['failed']);
        $this->assertSame('stop', $result['signal']);
    }

    public function testMemberInConflictingStateIsSkippedNotFailed(): void
    {
        [$area, $memberA, $memberB] = $this->makeArea();
        $memberB->update(['status' => Server::STATUS_SUSPENDED]);

        $this->powerRepository->allows('send')->with('start')->andReturnUsing(function () {
            $this->assertNotSame('member-b', $this->currentPowerServerName());

            return new Response();
        });
        $this->serverRepository->allows('getDetails')->andReturn(['state' => 'running']);

        $result = $this->service->send($area, 'start');

        $this->assertSame(['member-a', 'member-c', 'proxy'], $result['succeeded']);
        $this->assertSame(['member-b'], $result['skipped']);
        $this->assertSame([], $result['failed']);
    }

    public function testRestartStopsEverythingBeforeStartingAnything(): void
    {
        [$area] = $this->makeArea();

        $order = [];

        $this->powerRepository->allows('send')->andReturnUsing(function (string $signal) use (&$order) {
            $order[] = "$signal:{$this->currentPowerServerName()}";

            return new Response();
        });
        $this->serverRepository->allows('getDetails')->andReturn(['state' => 'running']);

        $result = $this->service->send($area, 'restart');

        $this->assertSame('restart', $result['signal']);

        // Every stop must have been sent before any start.
        $lastStopIndex = max(array_keys(array_filter($order, fn ($c) => str_starts_with($c, 'stop:'))));
        $firstStartIndex = min(array_keys(array_filter($order, fn ($c) => str_starts_with($c, 'start:'))));
        $this->assertLessThan($firstStartIndex, $lastStopIndex);

        $this->assertSame(['member-a', 'member-b', 'member-c', 'proxy'], $result['succeeded']);
    }

    /**
     * The counterpart to AreaPolicy::power()'s loosened "at least one server" gate: a user who can
     * only control part of an area must have the rest skipped here, never signalled. If this ever
     * regresses, a staff member with access to one server could power-cycle an entire area.
     */
    public function testServersTheActorCannotControlAreSkippedNotSignalled(): void
    {
        [$area, $memberA, , , $proxy] = $this->makeArea();

        $user = User::factory()->create();

        // Access to exactly one member, and none to the other members or the proxy.
        Subuser::query()->create([
            'user_id' => $user->id,
            'server_id' => $memberA->id,
            'permissions' => [Permission::ACTION_CONTROL_START],
        ]);

        $signalled = [];

        $this->powerRepository->allows('send')->with('start')->andReturnUsing(function () use (&$signalled) {
            $signalled[] = $this->currentPowerServerName();

            return new Response();
        });

        $this->serverRepository->allows('getDetails')->andReturn(['state' => 'running']);

        $result = $this->service->send($area, 'start', $user);

        // Only the one server the user actually controls was ever touched.
        $this->assertSame(['member-a'], $signalled);
        $this->assertSame(['member-a'], $result['succeeded']);
        $this->assertContains('member-b', $result['skipped']);
        $this->assertContains('member-c', $result['skipped']);
        $this->assertContains($proxy->name, $result['skipped']);
        $this->assertSame([], $result['failed']);
    }

    /**
     * Passing no actor is the internal/system path (a queued job or console command) and must not
     * filter anything — otherwise moving this service behind a queue would silently start dropping
     * servers from every area action.
     */
    public function testNullActorAppliesNoPermissionFiltering(): void
    {
        [$area] = $this->makeArea();

        $signalled = [];

        $this->powerRepository->allows('send')->with('start')->andReturnUsing(function () use (&$signalled) {
            $signalled[] = $this->currentPowerServerName();

            return new Response();
        });

        $this->serverRepository->allows('getDetails')->andReturn(['state' => 'running']);

        $result = $this->service->send($area, 'start', null);

        $this->assertSame(['member-a', 'member-b', 'member-c', 'proxy'], $signalled);
        $this->assertSame([], $result['skipped']);
    }
}
