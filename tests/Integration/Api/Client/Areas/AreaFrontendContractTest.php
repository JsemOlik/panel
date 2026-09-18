<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Areas;

use Ramsey\Uuid\Uuid;
use Pterodactyl\Models\Area;
use Pterodactyl\Models\User;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

/**
 * Locks the area endpoints to the exact JSON the frontend parsers read.
 *
 * The per-layer suites all pass while the feature is still broken in a browser, because each one
 * only ever sees its own side of the wire: the backend tests assert against PHP arrays and the
 * frontend compiles against hand-written TypeScript types that nothing checks against a real
 * response. Three separate defects reached the browser through exactly that gap — the transformer
 * emitting `area_role` while the parser read `role`, the client routes binding on uuid while the
 * frontend sent numeric ids, and the power endpoint returning its result flat while the client
 * helper unwrapped `data.attributes`.
 *
 * So each assertion below names the frontend file and the property access it protects. If you
 * change a key here, change it there in the same commit — a passing build will not tell you.
 */
class AreaFrontendContractTest extends ClientApiIntegrationTestCase
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
     * resources/scripts/api/areas/getArea.ts — rawDataToAreaObject() destructures `{ attributes }`
     * and then reads id, uuid, name, description and a FLAT `members` array off it. It is not a
     * nested Fractal relationship: reading data.relationships.members.data yields nothing.
     */
    public function testAreaDetailMatchesWhatTheFrontendParses(): void
    {
        $user = User::factory()->admin()->create();
        $area = $this->createArea(['name' => 'Kids Lobby', 'description' => 'Main play area']);

        $member = $this->createServerModel(['name' => 'member-a']);
        $proxy = $this->createServerModel(['name' => 'proxy']);

        $area->servers()->attach($member->id, ['role' => Area::ROLE_MEMBER, 'sort_order' => 1]);
        $area->servers()->attach($proxy->id, ['role' => Area::ROLE_PROXY, 'sort_order' => 0]);

        $response = $this->actingAs($user)
            ->getJson('/api/client/areas/' . $area->uuid)
            ->assertOk();

        $response->assertJsonPath('attributes.uuid', $area->uuid);
        $response->assertJsonPath('attributes.name', 'Kids Lobby');
        $response->assertJsonPath('attributes.description', 'Main play area');

        $json = $response->json();

        $this->assertArrayHasKey('members', $json['attributes'], 'getArea.ts reads attributes.members directly.');
        $this->assertIsList($json['attributes']['members'], 'members must be a flat list, not a Fractal relationship envelope.');
        $this->assertCount(2, $json['attributes']['members']);

        foreach ($json['attributes']['members'] as $rawMember) {
            // rawDataToAreaMember() reads these two pivot keys off the member itself.
            $this->assertArrayHasKey('area_role', $rawMember, 'getArea.ts reads member.area_role (not "role").');
            $this->assertArrayHasKey('area_sort_order', $rawMember, 'getArea.ts reads member.area_sort_order.');
            $this->assertContains($rawMember['area_role'], [Area::ROLE_MEMBER, Area::ROLE_PROXY]);

            // rawDataToServerObject() is then handed the same object. Every key it touches must be
            // present, because the members are rendered with the shared Server type.
            foreach ([
                'identifier', 'uuid', 'name', 'node', 'is_node_under_maintenance', 'status',
                'invocation', 'docker_image', 'limits', 'feature_limits', 'is_transferring',
                'sftp_details',
            ] as $key) {
                $this->assertArrayHasKey($key, $rawMember, "rawDataToServerObject() reads data.$key off each area member.");
            }

            // This one is dereferenced without a guard (data.sftp_details.ip), so a missing or null
            // sftp_details throws a TypeError in the browser rather than rendering a partial row.
            $this->assertIsArray($rawMember['sftp_details']);
            $this->assertArrayHasKey('ip', $rawMember['sftp_details']);
            $this->assertArrayHasKey('port', $rawMember['sftp_details']);
        }
    }

    /**
     * resources/scripts/api/areas/getAreas.ts reads `data.data` and maps each entry through
     * rawDataToAreaObject(), which expects the `{ object, attributes }` envelope on every item.
     */
    public function testAreaIndexMatchesWhatTheFrontendParses(): void
    {
        $user = User::factory()->admin()->create();
        $area = $this->createArea(['name' => 'Area One']);

        $json = $this->actingAs($user)
            ->getJson('/api/client/areas')
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('data', $json, 'getAreas.ts reads data.data.');
        $this->assertIsList($json['data']);
        $this->assertNotEmpty($json['data']);

        $first = $json['data'][0];
        $this->assertArrayHasKey('attributes', $first, 'Each item must carry the {object, attributes} envelope.');
        $this->assertSame($area->uuid, $first['attributes']['uuid']);

        // AreaListContainer.tsx links to `/areas/${area.uuid}` and shows `area.members.length`.
        $this->assertArrayHasKey('members', $first['attributes']);
        $this->assertIsList($first['attributes']['members']);
    }

    /**
     * resources/scripts/api/areas/sendAreaPowerAction.ts returns `data.attributes`, matching
     * sendBulkPowerAction.ts. A flat result here resolves to undefined in the browser, and
     * AreaPowerActions.tsx then renders a result summary off nothing.
     */
    public function testPowerResultIsWrappedInAnAttributesEnvelope(): void
    {
        $user = User::factory()->admin()->create();
        $area = $this->createArea();

        // Empty area: nothing is signalled, so this pins the response envelope rather than the
        // sequencing (AreaPowerActionServiceTest covers that against mocked daemons).
        $json = $this->actingAs($user)
            ->postJson('/api/client/areas/' . $area->uuid . '/power', ['signal' => 'start'])
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('attributes', $json, 'sendAreaPowerAction.ts returns data.attributes.');

        foreach (['signal', 'succeeded', 'skipped', 'failed'] as $key) {
            $this->assertArrayHasKey($key, $json['attributes'], "AreaPowerActions.tsx reads result.$key.");
        }

        $this->assertSame('start', $json['attributes']['signal']);
        $this->assertIsList($json['attributes']['succeeded']);
        $this->assertIsList($json['attributes']['skipped']);
        $this->assertIsList($json['attributes']['failed']);
    }
}
