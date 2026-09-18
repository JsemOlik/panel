<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Areas;

use Ramsey\Uuid\Uuid;
use Pterodactyl\Models\Area;
use Pterodactyl\Models\User;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

class AreaControllerTest extends ClientApiIntegrationTestCase
{
    protected function tearDown(): void
    {
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

    /**
     * The client API addresses areas by uuid, not by their numeric id: Pterodactyl's base model
     * returns 'uuid' from getRouteKeyName() and the /api/client/areas routes deliberately don't
     * override that to ':id' the way the admin routes do. A frontend that builds these URLs from
     * the numeric id gets "No query results for model [Area]" instead of the area, so both halves
     * of that contract are asserted here.
     */
    public function testAreaIsResolvedByUuidAndNotByNumericId(): void
    {
        $user = User::factory()->admin()->create();
        $area = $this->createArea(['name' => 'Kids Lobby']);

        $this->actingAs($user)
            ->getJson('/api/client/areas/' . $area->uuid)
            ->assertOk()
            ->assertJsonPath('attributes.uuid', $area->uuid)
            ->assertJsonPath('attributes.name', 'Kids Lobby');

        $this->actingAs($user)
            ->getJson('/api/client/areas/' . $area->id)
            ->assertNotFound();
    }

    public function testPowerEndpointIsAlsoAddressedByUuid(): void
    {
        $user = User::factory()->admin()->create();
        $area = $this->createArea();

        // An empty area has nothing to signal, so this exercises routing/binding rather than the
        // sequencing itself (which AreaPowerActionServiceTest covers against mocked daemons).
        $this->actingAs($user)
            ->postJson('/api/client/areas/' . $area->uuid . '/power', ['signal' => 'start'])
            ->assertOk();

        $this->actingAs($user)
            ->postJson('/api/client/areas/' . $area->id . '/power', ['signal' => 'start'])
            ->assertNotFound();
    }

    public function testIndexListsAreasWithTheirUuid(): void
    {
        $user = User::factory()->admin()->create();
        $area = $this->createArea(['name' => 'Area One']);

        $this->actingAs($user)
            ->getJson('/api/client/areas')
            ->assertOk()
            ->assertJsonPath('data.0.attributes.uuid', $area->uuid)
            ->assertJsonPath('data.0.attributes.name', 'Area One');
    }
}
