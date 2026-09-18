<?php

namespace Pterodactyl\Tests\Integration\Services\Consoles;

use Mockery;
use Ramsey\Uuid\Uuid;
use GuzzleHttp\Psr7\Response;
use Pterodactyl\Models\Area;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Subuser;
use Pterodactyl\Models\Permission;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\TransferException;
use Pterodactyl\Services\Consoles\AreaCommandService;
use Pterodactyl\Repositories\Wings\DaemonCommandRepository;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Exercises AreaCommandService against a real, database-backed Area/Server/pivot graph, with the
 * Wings-facing repository mocked out (no real daemon calls are made in this suite).
 */
class AreaCommandServiceTest extends IntegrationTestCase
{
    private Mockery\MockInterface $repository;

    private AreaCommandService $service;

    /** @var array<int, Server> The server each "setServer" call currently targets. */
    private array $servers = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(DaemonCommandRepository::class);
        $this->app->instance(DaemonCommandRepository::class, $this->repository);

        $this->repository->allows('setServer')->andReturnUsing(function (Server $server) {
            $this->servers[] = $server;

            return $this->repository;
        });

        $this->service = $this->app->make(AreaCommandService::class);
    }

    public function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function createArea(array $attributes = []): Area
    {
        return Area::query()->create(array_merge([
            'uuid' => Uuid::uuid4()->toString(),
            'name' => 'Kids Lobby',
        ], $attributes));
    }

    public function testSendsCommandToEveryServerInTheArea(): void
    {
        $area = $this->createArea();
        $memberA = $this->createServerModel(['name' => 'member-a']);
        $memberB = $this->createServerModel(['name' => 'member-b']);
        $proxy = $this->createServerModel(['name' => 'proxy']);

        $area->servers()->attach($memberA->id, ['role' => Area::ROLE_MEMBER, 'sort_order' => 1]);
        $area->servers()->attach($memberB->id, ['role' => Area::ROLE_MEMBER, 'sort_order' => 2]);
        $area->servers()->attach($proxy->id, ['role' => Area::ROLE_PROXY, 'sort_order' => 0]);

        $sentTo = [];
        $this->repository->allows('send')->with('say hi')->andReturnUsing(function () use (&$sentTo) {
            $sentTo[] = end($this->servers)->name;

            return new Response();
        });

        $result = $this->service->send($area, 'say hi');

        $this->assertSame('say hi', $result['command']);
        $this->assertSame(['member-a', 'member-b', 'proxy'], $sentTo);
        $this->assertCount(3, $result['succeeded']);
        $this->assertSame([], $result['skipped']);
        $this->assertSame([], $result['failed']);

        foreach ($result['succeeded'] as $entry) {
            $this->assertArrayHasKey('id', $entry);
            $this->assertArrayHasKey('uuid', $entry);
            $this->assertArrayHasKey('name', $entry);
        }
    }

    public function testSuspendedServerIsSkippedNotFailed(): void
    {
        $area = $this->createArea();
        $member = $this->createServerModel(['name' => 'member-a']);
        $suspended = $this->createServerModel(['name' => 'member-b', 'status' => Server::STATUS_SUSPENDED]);

        $area->servers()->attach($member->id, ['role' => Area::ROLE_MEMBER]);
        $area->servers()->attach($suspended->id, ['role' => Area::ROLE_MEMBER]);

        $this->repository->allows('send')->andReturn(new Response());

        $result = $this->service->send($area, 'say hi');

        $this->assertSame(['member-a'], array_column($result['succeeded'], 'name'));
        $this->assertSame(['member-b'], array_column($result['skipped'], 'name'));
        $this->assertSame('suspended', $result['skipped'][0]['reason']);
    }

    public function testDaemonConnectionFailureIsReportedAsFailedWithReason(): void
    {
        $area = $this->createArea();
        $offline = $this->createServerModel(['name' => 'offline-server']);
        $unreachable = $this->createServerModel(['name' => 'unreachable-server']);

        $area->servers()->attach($offline->id, ['role' => Area::ROLE_MEMBER]);
        $area->servers()->attach($unreachable->id, ['role' => Area::ROLE_MEMBER]);

        $badGateway = Mockery::mock(BadResponseException::class);
        $badGateway->allows('getResponse')->andReturn(new Response(502));

        $this->repository->allows('send')->andReturnUsing(function () use (&$badGateway) {
            $server = end($this->servers);

            if ($server->name === 'offline-server') {
                throw new DaemonConnectionException($badGateway);
            }

            throw new DaemonConnectionException(new TransferException('connection refused'));
        });

        $result = $this->service->send($area, 'stop');

        $failedByName = [];
        foreach ($result['failed'] as $entry) {
            $failedByName[$entry['name']] = $entry['reason'];
        }

        $this->assertSame('offline', $failedByName['offline-server']);
        $this->assertSame('unreachable', $failedByName['unreachable-server']);
        $this->assertSame([], $result['succeeded']);
    }

    /**
     * The counterpart to AreaPolicy::command()'s loosened "at least one server" gate: a user who
     * can only send console commands to part of an area must have the rest skipped here, never
     * signalled. If this regresses, a staff member with control.console on one server could
     * broadcast a command to an entire area.
     */
    public function testServersTheActorCannotControlAreSkippedNotSignalled(): void
    {
        $area = $this->createArea();
        $allowed = $this->createServerModel(['name' => 'allowed']);
        $denied = $this->createServerModel(['name' => 'denied']);

        $area->servers()->attach($allowed->id, ['role' => Area::ROLE_MEMBER]);
        $area->servers()->attach($denied->id, ['role' => Area::ROLE_MEMBER]);

        $user = User::factory()->create();
        Subuser::query()->create([
            'user_id' => $user->id,
            'server_id' => $allowed->id,
            'permissions' => [Permission::ACTION_CONTROL_CONSOLE],
        ]);

        $this->repository->allows('send')->andReturnUsing(function () {
            $this->assertNotSame('denied', end($this->servers)->name);

            return new Response();
        });

        $result = $this->service->send($area, 'say hi', $user);

        $this->assertSame(['allowed'], array_column($result['succeeded'], 'name'));
        $this->assertSame(['denied'], array_column($result['skipped'], 'name'));
        $this->assertSame('no_permission', $result['skipped'][0]['reason']);
    }

    public function testNullActorSkipsPermissionFiltering(): void
    {
        $area = $this->createArea();
        $server = $this->createServerModel(['name' => 'server-a']);
        $area->servers()->attach($server->id, ['role' => Area::ROLE_MEMBER]);

        $this->repository->allows('send')->andReturn(new Response());

        $result = $this->service->send($area, 'say hi', null);

        $this->assertSame(['server-a'], array_column($result['succeeded'], 'name'));
    }
}
