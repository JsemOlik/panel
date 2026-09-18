<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin\AreaController;

use Ramsey\Uuid\Uuid;
use Pterodactyl\Models\Area;
use Pterodactyl\Models\User;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;

class AreaServerCrudTest extends HttpTestCase
{
    private function createAreaModel(array $attributes = []): Area
    {
        return Area::query()->create($attributes + [
            'uuid' => Uuid::uuid4()->toString(),
            'name' => 'Test Area ' . Uuid::uuid4()->toString(),
        ]);
    }

    public function testAdminCanAttachAServerToAnArea(): void
    {
        $admin = User::factory()->admin()->create();
        $area = $this->createAreaModel();
        $server = $this->createServerModel();

        $this->actingAs($admin)
            ->post('/admin/areas/view/' . $area->id . '/servers', [
                'server_id' => $server->id,
                'role' => Area::ROLE_MEMBER,
                'sort_order' => 1,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/areas/view/' . $area->id);

        $this->assertDatabaseHas('area_server', [
            'area_id' => $area->id,
            'server_id' => $server->id,
            'role' => Area::ROLE_MEMBER,
            'sort_order' => 1,
        ]);
    }

    public function testAreaCannotHaveMoreThanOneProxyServer(): void
    {
        $admin = User::factory()->admin()->create();
        $area = $this->createAreaModel();
        $proxy = $this->createServerModel();
        $secondProxy = $this->createServerModel();

        $area->servers()->attach($proxy->id, ['role' => Area::ROLE_PROXY, 'sort_order' => 0]);

        $this->actingAs($admin)
            ->withHeaders(['Accept' => 'text/html'])
            ->post('/admin/areas/view/' . $area->id . '/servers', [
                'server_id' => $secondProxy->id,
                'role' => Area::ROLE_PROXY,
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('area_server', ['server_id' => $secondProxy->id]);
    }

    public function testAdminCanChangeAServersRoleWithinAnArea(): void
    {
        $admin = User::factory()->admin()->create();
        $area = $this->createAreaModel();
        $server = $this->createServerModel();

        $area->servers()->attach($server->id, ['role' => Area::ROLE_MEMBER, 'sort_order' => 0]);
        $pivotId = DB::table('area_server')->where('area_id', $area->id)->where('server_id', $server->id)->value('id');

        $this->actingAs($admin)
            ->patch('/admin/areas/view/' . $area->id . '/servers/' . $pivotId, [
                'role' => Area::ROLE_PROXY,
                'sort_order' => 0,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('area_server', ['id' => $pivotId, 'role' => Area::ROLE_PROXY]);
    }

    public function testUpdatingAPivotFromAnotherAreaReturns404(): void
    {
        $admin = User::factory()->admin()->create();
        $areaOne = $this->createAreaModel();
        $areaTwo = $this->createAreaModel();
        $server = $this->createServerModel();

        $areaOne->servers()->attach($server->id, ['role' => Area::ROLE_MEMBER, 'sort_order' => 0]);
        $pivotId = DB::table('area_server')->where('area_id', $areaOne->id)->value('id');

        $this->actingAs($admin)
            ->patch('/admin/areas/view/' . $areaTwo->id . '/servers/' . $pivotId, [
                'role' => Area::ROLE_MEMBER,
            ])
            ->assertNotFound();
    }

    public function testAdminCanDetachAServerFromAnArea(): void
    {
        $admin = User::factory()->admin()->create();
        $area = $this->createAreaModel();
        $server = $this->createServerModel();

        $area->servers()->attach($server->id, ['role' => Area::ROLE_MEMBER, 'sort_order' => 0]);
        $pivotId = DB::table('area_server')->where('area_id', $area->id)->value('id');

        $this->actingAs($admin)
            ->delete('/admin/areas/view/' . $area->id . '/servers/' . $pivotId)
            ->assertRedirect('/admin/areas/view/' . $area->id);

        $this->assertDatabaseMissing('area_server', ['id' => $pivotId]);
    }

    /**
     * Rendering the area detail page builds route URLs for each attached server's pivot row, which
     * requires the pivot's `id` to actually be selected by the Area::servers() relation. It is not
     * selected by default, so a missing withPivot('id') breaks the page with a UrlGenerationException
     * rather than failing any of the endpoint-level tests above.
     */
    public function testAreaViewPageRendersWithAttachedServers(): void
    {
        $admin = User::factory()->admin()->create();
        $area = $this->createAreaModel();
        $member = $this->createServerModel();
        $proxy = $this->createServerModel();

        $area->servers()->attach($member->id, ['role' => Area::ROLE_MEMBER, 'sort_order' => 1]);
        $area->servers()->attach($proxy->id, ['role' => Area::ROLE_PROXY, 'sort_order' => 0]);

        $pivotIds = DB::table('area_server')->where('area_id', $area->id)->pluck('id');
        $this->assertCount(2, $pivotIds);

        $response = $this->actingAs($admin)
            ->withHeaders(['Accept' => 'text/html'])
            ->get('/admin/areas/view/' . $area->id)
            ->assertOk();

        // Each pivot row must be addressable from the rendered page.
        foreach ($pivotIds as $pivotId) {
            $response->assertSee('/admin/areas/view/' . $area->id . '/servers/' . $pivotId, false);
        }
    }
}
