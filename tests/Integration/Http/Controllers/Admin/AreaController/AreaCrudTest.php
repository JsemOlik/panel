<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin\AreaController;

use Ramsey\Uuid\Uuid;
use Pterodactyl\Models\Area;
use Pterodactyl\Models\User;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;

class AreaCrudTest extends HttpTestCase
{
    /**
     * Areas don't have a model factory (the model is owned by a different agent), so build one
     * directly here the same way the admin controller does.
     */
    private function createAreaModel(array $attributes = []): Area
    {
        return Area::query()->create($attributes + [
            'uuid' => Uuid::uuid4()->toString(),
            'name' => 'Test Area ' . Uuid::uuid4()->toString(),
        ]);
    }

    public function testNonAdminCannotAccessEndpoints(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/areas')->assertForbidden();
        $this->actingAs($user)->post('/admin/areas/new', ['name' => 'Test Area'])->assertForbidden();
    }

    public function testAdminCanCreateAnArea(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/admin/areas/new', [
            'name' => 'Lobby Cluster A',
            'description' => 'Kid-facing lobby servers.',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        /** @var Area $area */
        $area = Area::query()->where('name', 'Lobby Cluster A')->firstOrFail();

        $this->assertSame('Kid-facing lobby servers.', $area->description);
        $this->assertNotEmpty($area->uuid);
        $this->assertSame(36, strlen($area->uuid));
    }

    public function testNameIsRequiredToCreateAnArea(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->withHeaders(['Accept' => 'text/html'])
            ->post('/admin/areas/new', ['description' => 'Missing a name'])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseMissing('areas', ['description' => 'Missing a name']);
    }

    public function testAdminCanViewAnArea(): void
    {
        $admin = User::factory()->admin()->create();
        $area = $this->createAreaModel();

        $this->actingAs($admin)
            ->get('/admin/areas/view/' . $area->id)
            ->assertOk();
    }

    public function testAdminCanUpdateAnArea(): void
    {
        $admin = User::factory()->admin()->create();
        $area = $this->createAreaModel(['name' => 'Old Name']);

        $this->actingAs($admin)
            ->patch('/admin/areas/view/' . $area->id, ['name' => 'New Name', 'description' => null])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/areas/view/' . $area->id);

        $this->assertSame('New Name', $area->fresh()->name);
    }

    public function testAdminCanDeleteAnArea(): void
    {
        $admin = User::factory()->admin()->create();
        $area = $this->createAreaModel();

        $this->actingAs($admin)
            ->delete('/admin/areas/view/' . $area->id)
            ->assertRedirect('/admin/areas');

        $this->assertNull(Area::query()->find($area->id));
    }
}
