<?php

namespace Pterodactyl\Tests\Integration\Api\Client\ConsoleArchive;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Subuser;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\ServerConsoleArchive;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

/**
 * Locks the console-archive endpoint to the exact JSON resources/scripts/api/server/consoleArchive.ts
 * and its transformer (resources/scripts/api/definitions/consoleArchive/transformers.ts) parse, and
 * locks down who is allowed to read it — see tests/Integration/Api/Client/Areas/AreaFrontendContractTest.php
 * for why this class of test exists in this fork: the per-layer suites pass individually while a key
 * mismatch between the PHP transformer and the hand-written TypeScript parser still breaks the browser.
 */
class ConsoleArchiveFrontendContractTest extends ClientApiIntegrationTestCase
{
    protected function tearDown(): void
    {
        ServerConsoleArchive::query()->delete();

        parent::tearDown();
    }

    public function testEntryShapeMatchesWhatTheFrontendParses(): void
    {
        $server = $this->createServerModel();

        $entry = ServerConsoleArchive::query()->create([
            'server_id' => $server->id,
            'logged_at' => '2026-01-01 12:00:00',
            'line' => '[12:00:00] [Server thread/INFO]: <Steve> can we build a house?',
            'source' => ServerConsoleArchive::SOURCE_CHAT,
            'player' => 'Steve',
        ]);

        $json = $this->actingAs($server->user)
            ->getJson('/api/client/servers/' . $server->uuid . '/console-archive')
            ->assertOk()
            ->json();

        // consoleArchive.ts's toPaginatedSet() reads data.data, and each item through the
        // {object, attributes} envelope, matching every other paginated client endpoint.
        $this->assertArrayHasKey('data', $json, 'toPaginatedSet() reads data.data.');
        $this->assertIsList($json['data']);
        $this->assertCount(1, $json['data']);

        $attributes = $json['data'][0]['attributes'];

        // Transformers.toConsoleArchiveEntry() reads exactly these keys off `attributes`.
        foreach (['id', 'logged_at', 'line', 'source', 'player'] as $key) {
            $this->assertArrayHasKey($key, $attributes, "toConsoleArchiveEntry() reads attributes.$key.");
        }

        $this->assertSame($entry->id, $attributes['id']);
        $this->assertSame('[12:00:00] [Server thread/INFO]: <Steve> can we build a house?', $attributes['line']);
        $this->assertSame('chat', $attributes['source']);
        $this->assertSame('Steve', $attributes['player']);

        // loggedAt: new Date(attributes.logged_at) — must be a string Date() can parse, not an
        // object or a null.
        $this->assertIsString($attributes['logged_at']);
        $this->assertNotFalse(strtotime($attributes['logged_at']));
    }

    public function testSearchFiltersByFullTextQuery(): void
    {
        $server = $this->createServerModel();

        ServerConsoleArchive::query()->create([
            'server_id' => $server->id,
            'logged_at' => '2026-01-01 12:00:00',
            'line' => '<Steve> lets meet at the river',
            'source' => ServerConsoleArchive::SOURCE_CHAT,
            'player' => 'Steve',
        ]);
        ServerConsoleArchive::query()->create([
            'server_id' => $server->id,
            'logged_at' => '2026-01-01 12:01:00',
            'line' => '<Alex> heading to the mountains',
            'source' => ServerConsoleArchive::SOURCE_CHAT,
            'player' => 'Alex',
        ]);

        $json = $this->actingAs($server->user)
            ->getJson('/api/client/servers/' . $server->uuid . '/console-archive?query=mountains')
            ->assertOk()
            ->json();

        $this->assertCount(1, $json['data']);
        $this->assertSame('Alex', $json['data'][0]['attributes']['player']);
    }

    public function testEntriesAreScopedToTheRequestedServerOnly(): void
    {
        $server = $this->createServerModel();
        $otherServer = $this->createServerModel();

        ServerConsoleArchive::query()->create([
            'server_id' => $otherServer->id,
            'logged_at' => '2026-01-01 12:00:00',
            'line' => 'a line that belongs to a different server',
            'source' => ServerConsoleArchive::SOURCE_CONSOLE,
        ]);

        $json = $this->actingAs($server->user)
            ->getJson('/api/client/servers/' . $server->uuid . '/console-archive')
            ->assertOk()
            ->json();

        $this->assertSame([], $json['data']);
    }

    public function testSubuserWithoutTheArchivePermissionIsDenied(): void
    {
        $server = $this->createServerModel();
        $user = User::factory()->create();

        Subuser::query()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            // Deliberately has console/control access but NOT archive.read, to pin that this
            // permission is not implied by other server access.
            'permissions' => [Permission::ACTION_WEBSOCKET_CONNECT, Permission::ACTION_CONTROL_CONSOLE],
        ]);

        $this->actingAs($user)
            ->getJson('/api/client/servers/' . $server->uuid . '/console-archive')
            ->assertForbidden();
    }

    public function testSubuserGrantedTheArchivePermissionCanRead(): void
    {
        $server = $this->createServerModel();
        $user = User::factory()->create();

        Subuser::query()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'permissions' => [Permission::ACTION_ARCHIVE_READ],
        ]);

        $this->actingAs($user)
            ->getJson('/api/client/servers/' . $server->uuid . '/console-archive')
            ->assertOk();
    }
}
