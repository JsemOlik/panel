<?php

namespace Pterodactyl\Tests\Integration\Api\Client;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use GuzzleHttp\Exception\TransferException;
use Pterodactyl\Repositories\Wings\DaemonPowerRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

class BulkPowerControllerTest extends ClientApiIntegrationTestCase
{
    public function testInvalidSignalIsRejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/client/servers/power', ['signal' => 'invalid'])
            ->assertUnprocessable();
    }

    public function testActionIsSentToOwnedServersOnly(): void
    {
        [$user, $running] = $this->generateTestAccount();
        $running->update(['name' => 'A running']);

        $suspended = $this->createServerModel(['user_id' => $user->id, 'name' => 'B suspended', 'status' => Server::STATUS_SUSPENDED]);
        $unreachable = $this->createServerModel(['user_id' => $user->id, 'name' => 'C unreachable']);

        // A server the user can only access as a subuser, which must not be touched.
        [$subuser, $shared] = $this->generateTestAccount(['control.start']);
        $shared->subusers()->update(['user_id' => $user->id]);

        $repository = \Mockery::mock(DaemonPowerRepository::class);
        $this->app->instance(DaemonPowerRepository::class, $repository);

        $sent = [];
        $repository->allows('setServer')->andReturnUsing(function (Server $server) use (&$sent, $repository) {
            $sent[] = $server->uuid;

            return $repository;
        });
        $repository->allows('send')->with('restart')->andReturnUsing(function () use (&$sent, $unreachable) {
            if (end($sent) === $unreachable->uuid) {
                throw new DaemonConnectionException(new TransferException('Unreachable'));
            }

            return new \GuzzleHttp\Psr7\Response();
        });

        $this->actingAs($user)
            ->postJson('/api/client/servers/power', ['signal' => 'restart'])
            ->assertOk()
            ->assertJsonPath('attributes.succeeded', ['A running'])
            ->assertJsonPath('attributes.skipped', ['B suspended'])
            ->assertJsonPath('attributes.failed', ['C unreachable']);

        $this->assertSame([$running->uuid, $unreachable->uuid], $sent);
        $this->assertNotContains($shared->uuid, $sent);
        $this->assertNotContains($suspended->uuid, $sent);

        $this->assertDatabaseHas('activity_logs', ['event' => 'server:power.restart', 'actor_id' => $user->id]);
    }
}
