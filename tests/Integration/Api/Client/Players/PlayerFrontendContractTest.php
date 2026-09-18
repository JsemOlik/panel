<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Players;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Subuser;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\ServerPlayer;
use Pterodactyl\Models\ServerPlayerSession;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

/**
 * Locks the player-roster and player-session endpoints to the exact JSON the frontend parsers
 * read (resources/scripts/api/definitions/players/*), and locks down who is allowed to read it —
 * see tests/Integration/Api/Client/Areas/AreaFrontendContractTest.php for why this class of test
 * exists in this fork.
 */
class PlayerFrontendContractTest extends ClientApiIntegrationTestCase
{
    protected function tearDown(): void
    {
        ServerPlayer::query()->delete();
        ServerPlayerSession::query()->delete();

        parent::tearDown();
    }

    public function testRosterShapeMatchesWhatTheFrontendParses(): void
    {
        $server = $this->createServerModel();

        $player = ServerPlayer::query()->create([
            'server_id' => $server->id,
            'name' => 'Steve',
            'status' => ServerPlayer::STATUS_ONLINE,
            'joined_at' => '2026-01-01 12:00:00',
            'last_seen_at' => '2026-01-01 12:00:00',
        ]);

        $json = $this->actingAs($server->user)
            ->getJson('/api/client/servers/' . $server->uuid . '/players')
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('data', $json, 'toPlayerList() reads data.data.');
        $this->assertIsList($json['data']);
        $this->assertCount(1, $json['data']);

        $attributes = $json['data'][0]['attributes'];

        foreach (['id', 'name', 'status', 'joined_at', 'last_seen_at'] as $key) {
            $this->assertArrayHasKey($key, $attributes, "toServerPlayer() reads attributes.$key.");
        }

        $this->assertSame($player->id, $attributes['id']);
        $this->assertSame('Steve', $attributes['name']);
        $this->assertSame('online', $attributes['status']);
        $this->assertIsString($attributes['joined_at']);
        $this->assertNotFalse(strtotime($attributes['joined_at']));
    }

    public function testRosterCanBeFilteredByStatus(): void
    {
        $server = $this->createServerModel();

        ServerPlayer::query()->create([
            'server_id' => $server->id,
            'name' => 'Steve',
            'status' => ServerPlayer::STATUS_ONLINE,
            'last_seen_at' => '2026-01-01 12:00:00',
        ]);
        ServerPlayer::query()->create([
            'server_id' => $server->id,
            'name' => 'Alex',
            'status' => ServerPlayer::STATUS_OFFLINE,
            'last_seen_at' => '2026-01-01 11:00:00',
        ]);

        $json = $this->actingAs($server->user)
            ->getJson('/api/client/servers/' . $server->uuid . '/players?status=online')
            ->assertOk()
            ->json();

        $this->assertCount(1, $json['data']);
        $this->assertSame('Steve', $json['data'][0]['attributes']['name']);
    }

    public function testRosterIsScopedToTheRequestedServerOnly(): void
    {
        $server = $this->createServerModel();
        $otherServer = $this->createServerModel();

        ServerPlayer::query()->create([
            'server_id' => $otherServer->id,
            'name' => 'Steve',
            'status' => ServerPlayer::STATUS_ONLINE,
            'last_seen_at' => '2026-01-01 12:00:00',
        ]);

        $json = $this->actingAs($server->user)
            ->getJson('/api/client/servers/' . $server->uuid . '/players')
            ->assertOk()
            ->json();

        $this->assertSame([], $json['data']);
    }

    public function testPlayerSessionsShapeMatchesWhatTheFrontendParses(): void
    {
        $server = $this->createServerModel();

        $session = ServerPlayerSession::query()->create([
            'server_id' => $server->id,
            'name' => 'Steve',
            'event' => ServerPlayerSession::EVENT_JOIN,
            'occurred_at' => '2026-01-01 12:00:00',
        ]);

        $json = $this->actingAs($server->user)
            ->getJson('/api/client/servers/' . $server->uuid . '/players/Steve/sessions')
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('data', $json);
        $this->assertCount(1, $json['data']);

        $attributes = $json['data'][0]['attributes'];
        foreach (['id', 'name', 'event', 'occurred_at'] as $key) {
            $this->assertArrayHasKey($key, $attributes, "toServerPlayerSession() reads attributes.$key.");
        }

        $this->assertSame($session->id, $attributes['id']);
        $this->assertSame('join', $attributes['event']);
    }

    public function testSubuserWithoutThePlayersPermissionIsDenied(): void
    {
        $server = $this->createServerModel();
        $user = User::factory()->create();

        Subuser::query()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            // Has console access but NOT players.read, to pin that this permission is not implied
            // by other server access.
            'permissions' => [Permission::ACTION_WEBSOCKET_CONNECT, Permission::ACTION_CONTROL_CONSOLE],
        ]);

        $this->actingAs($user)
            ->getJson('/api/client/servers/' . $server->uuid . '/players')
            ->assertForbidden();
    }

    public function testSubuserGrantedThePlayersPermissionCanRead(): void
    {
        $server = $this->createServerModel();
        $user = User::factory()->create();

        Subuser::query()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'permissions' => [Permission::ACTION_PLAYERS_READ],
        ]);

        $this->actingAs($user)
            ->getJson('/api/client/servers/' . $server->uuid . '/players')
            ->assertOk();
    }
}
