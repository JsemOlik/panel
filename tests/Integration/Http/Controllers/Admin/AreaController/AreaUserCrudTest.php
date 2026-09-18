<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin\AreaController;

use Ramsey\Uuid\Uuid;
use Pterodactyl\Models\Area;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Subuser;
use Pterodactyl\Models\Permission;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;

class AreaUserCrudTest extends HttpTestCase
{
    private function createAreaModel(array $attributes = []): Area
    {
        return Area::query()->create($attributes + [
            'uuid' => Uuid::uuid4()->toString(),
            'name' => 'Test Area ' . Uuid::uuid4()->toString(),
        ]);
    }

    public function testAdminCanAssignStaffToAnArea(): void
    {
        $admin = User::factory()->admin()->create();
        $area = $this->createAreaModel();
        $staff = User::factory()->create();

        $this->actingAs($admin)
            ->post('/admin/areas/view/' . $area->id . '/staff', ['user_id' => $staff->id])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/areas/view/' . $area->id);

        $this->assertDatabaseHas('area_user', ['area_id' => $area->id, 'user_id' => $staff->id]);
        $this->assertDatabaseMissing('subusers', ['user_id' => $staff->id]);
    }

    public function testAssigningStaffCanAlsoGrantSubuserAccessToMemberServers(): void
    {
        $admin = User::factory()->admin()->create();
        $area = $this->createAreaModel();
        $staff = User::factory()->create();

        $memberOne = $this->createServerModel();
        $memberTwo = $this->createServerModel();
        $proxy = $this->createServerModel();
        // The staff member already owns this server — they should not get a redundant subuser row.
        $owned = $this->createServerModel(['user_id' => $staff->id]);

        $area->servers()->attach($memberOne->id, ['role' => Area::ROLE_MEMBER, 'sort_order' => 0]);
        $area->servers()->attach($memberTwo->id, ['role' => Area::ROLE_MEMBER, 'sort_order' => 1]);
        $area->servers()->attach($owned->id, ['role' => Area::ROLE_MEMBER, 'sort_order' => 2]);
        $area->servers()->attach($proxy->id, ['role' => Area::ROLE_PROXY, 'sort_order' => 0]);

        $this->actingAs($admin)
            ->post('/admin/areas/view/' . $area->id . '/staff', [
                'user_id' => $staff->id,
                'grant_subuser_access' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('area_user', ['area_id' => $area->id, 'user_id' => $staff->id]);

        foreach ([$memberOne, $memberTwo] as $server) {
            /** @var Subuser $subuser */
            $subuser = Subuser::query()->where('user_id', $staff->id)->where('server_id', $server->id)->firstOrFail();
            $this->assertContains(Permission::ACTION_CONTROL_CONSOLE, $subuser->permissions);
            $this->assertContains(Permission::ACTION_CONTROL_START, $subuser->permissions);
            $this->assertContains(Permission::ACTION_CONTROL_STOP, $subuser->permissions);
            $this->assertContains(Permission::ACTION_CONTROL_RESTART, $subuser->permissions);
        }

        // Proxy is not a "member" server, so no subuser row should have been created for it.
        $this->assertDatabaseMissing('subusers', ['user_id' => $staff->id, 'server_id' => $proxy->id]);
        // The staff member owns this server already, so no subuser row should exist for it either.
        $this->assertDatabaseMissing('subusers', ['user_id' => $staff->id, 'server_id' => $owned->id]);
    }

    public function testAdminCanRemoveStaffFromAnArea(): void
    {
        $admin = User::factory()->admin()->create();
        $area = $this->createAreaModel();
        $staff = User::factory()->create();

        $area->staff()->attach($staff->id);

        $this->actingAs($admin)
            ->delete('/admin/areas/view/' . $area->id . '/staff/' . $staff->id)
            ->assertRedirect('/admin/areas/view/' . $area->id);

        $this->assertDatabaseMissing('area_user', ['area_id' => $area->id, 'user_id' => $staff->id]);
    }
}
