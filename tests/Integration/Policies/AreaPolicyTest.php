<?php

namespace Pterodactyl\Tests\Integration\Policies;

use Ramsey\Uuid\Uuid;
use Pterodactyl\Models\Area;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Subuser;
use Illuminate\Support\Facades\Gate;
use Pterodactyl\Models\Permission;
use Pterodactyl\Tests\Integration\IntegrationTestCase;

class AreaPolicyTest extends IntegrationTestCase
{
    /**
     * Cleanup after running tests.
     */
    protected function tearDown(): void
    {
        Area::query()->forceDelete();
        Server::query()->forceDelete();
        User::query()->forceDelete();

        parent::tearDown();
    }

    private function createArea(array $attributes = []): Area
    {
        return Area::create(array_merge([
            'uuid' => Uuid::uuid4()->toString(),
            'name' => 'Test Area',
        ], $attributes));
    }

    public function testRootAdminCanAlwaysViewAndPowerAnArea(): void
    {
        $admin = User::factory()->admin()->create();
        $area = $this->createArea();
        $server = $this->createServerModel();
        $area->servers()->attach($server->id, ['role' => Area::ROLE_MEMBER]);

        $this->assertTrue(Gate::forUser($admin)->allows('view', $area));
        $this->assertTrue(Gate::forUser($admin)->allows('power', [$area, 'start']));
        $this->assertTrue(Gate::forUser($admin)->allows('power', [$area, 'stop']));
        $this->assertTrue(Gate::forUser($admin)->allows('power', [$area, 'restart']));
    }

    public function testUserWithNoRelationshipCannotViewOrPowerAnArea(): void
    {
        $user = User::factory()->create();
        $area = $this->createArea();
        $server = $this->createServerModel();
        $area->servers()->attach($server->id, ['role' => Area::ROLE_MEMBER]);

        $this->assertFalse(Gate::forUser($user)->allows('view', $area));
        $this->assertFalse(Gate::forUser($user)->allows('power', [$area, 'start']));
    }

    public function testAssignedStaffCanViewAreaEvenWithoutServerAccess(): void
    {
        $user = User::factory()->create();
        $area = $this->createArea();
        $server = $this->createServerModel();
        $area->servers()->attach($server->id, ['role' => Area::ROLE_MEMBER]);
        $area->staff()->attach($user->id);

        $this->assertTrue(Gate::forUser($user)->allows('view', $area));
        // Staff assignment alone does not grant power access — that still requires the
        // matching control.* permission on at least one of the area's servers.
        $this->assertFalse(Gate::forUser($user)->allows('power', [$area, 'start']));
    }

    public function testServerOwnerCanViewAndPowerAnAreaTheyOwnAllMembersOf(): void
    {
        $user = User::factory()->create();
        $area = $this->createArea();
        $server = $this->createServerModel(['user_id' => $user->id]);
        $area->servers()->attach($server->id, ['role' => Area::ROLE_MEMBER]);

        $this->assertTrue(Gate::forUser($user)->allows('view', $area));
        $this->assertTrue(Gate::forUser($user)->allows('power', [$area, 'start']));
        $this->assertTrue(Gate::forUser($user)->allows('power', [$area, 'stop']));
        $this->assertTrue(Gate::forUser($user)->allows('power', [$area, 'restart']));
    }

    public function testSubuserWithPartialAccessMayStillPowerTheArea(): void
    {
        $user = User::factory()->create();
        $area = $this->createArea();

        $memberOne = $this->createServerModel();
        Subuser::query()->create([
            'user_id' => $user->id,
            'server_id' => $memberOne->id,
            'permissions' => [Permission::ACTION_WEBSOCKET_CONNECT, Permission::ACTION_CONTROL_START],
        ]);

        // A second member the user has no relationship to at all.
        $memberTwo = $this->createServerModel();

        $area->servers()->attach($memberOne->id, ['role' => Area::ROLE_MEMBER]);
        $area->servers()->attach($memberTwo->id, ['role' => Area::ROLE_PROXY]);

        // Has websocket.connect on at least one member -> can view.
        $this->assertTrue(Gate::forUser($user)->allows('view', $area));

        // The gate only requires the permission on at least one server, so a staff member with
        // access to part of the area still gets a working button. AreaPowerActionService is what
        // keeps them from actually touching memberTwo — see AreaPowerActionServiceTest.
        $this->assertTrue(Gate::forUser($user)->allows('power', [$area, 'start']));

        // control.stop was never granted anywhere in the area, so stop remains denied outright.
        $this->assertFalse(Gate::forUser($user)->allows('power', [$area, 'stop']));

        // Granting control.start on the second member changes nothing about the gate's answer.
        Subuser::query()->create([
            'user_id' => $user->id,
            'server_id' => $memberTwo->id,
            'permissions' => [Permission::ACTION_CONTROL_START],
        ]);
        $area->load('servers');

        $this->assertTrue(Gate::forUser($user)->allows('power', [$area, 'start']));
        $this->assertFalse(Gate::forUser($user)->allows('power', [$area, 'stop']));
    }

    public function testPowerIsDeniedForAnAreaWithNoServers(): void
    {
        $user = User::factory()->admin()->create();
        $area = $this->createArea();

        // Even a root admin can't be granted a power action against an empty area since it's
        // ambiguous, but before() short-circuits admins to true at the ability level, so this
        // exercises the non-admin path instead.
        $regular = User::factory()->create();
        $this->assertFalse(Gate::forUser($regular)->allows('power', [$area, 'start']));
    }

    public function testRootAdminCanAlwaysCommandAnArea(): void
    {
        $admin = User::factory()->admin()->create();
        $area = $this->createArea();
        $server = $this->createServerModel();
        $area->servers()->attach($server->id, ['role' => Area::ROLE_MEMBER]);

        $this->assertTrue(Gate::forUser($admin)->allows('command', $area));
    }

    public function testUserWithNoConsolePermissionCannotCommandAnArea(): void
    {
        $user = User::factory()->create();
        $area = $this->createArea();
        $server = $this->createServerModel();
        $area->servers()->attach($server->id, ['role' => Area::ROLE_MEMBER]);

        $this->assertFalse(Gate::forUser($user)->allows('command', $area));
    }

    public function testSubuserWithConsolePermissionOnAnyMemberMayCommandTheArea(): void
    {
        $user = User::factory()->create();
        $area = $this->createArea();

        $memberOne = $this->createServerModel();
        Subuser::query()->create([
            'user_id' => $user->id,
            'server_id' => $memberOne->id,
            'permissions' => [Permission::ACTION_CONTROL_CONSOLE],
        ]);

        // A second member the user has no relationship to at all.
        $memberTwo = $this->createServerModel();

        $area->servers()->attach($memberOne->id, ['role' => Area::ROLE_MEMBER]);
        $area->servers()->attach($memberTwo->id, ['role' => Area::ROLE_PROXY]);

        // The gate only requires control.console on at least one server — AreaCommandService is
        // what keeps this user from actually reaching memberTwo (see AreaCommandServiceTest).
        $this->assertTrue(Gate::forUser($user)->allows('command', $area));
    }

    public function testCommandIsDeniedForAnAreaWithNoServers(): void
    {
        $area = $this->createArea();
        $regular = User::factory()->create();

        $this->assertFalse(Gate::forUser($regular)->allows('command', $area));
    }
}
