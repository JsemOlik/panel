<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Players;

use Mockery;
use GuzzleHttp\Psr7\Request;
use Illuminate\Http\Response;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\ServerPlayer;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Pterodactyl\Repositories\Wings\DaemonCommandRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

/**
 * The moderation endpoint turns a player row and a bit of text into a console command, so it hands
 * a narrow slice of console access to holders of `players.moderate`. These tests pin that slice:
 * what it can send, who may send it, and that nothing in the request body can widen it into
 * arbitrary console control.
 */
class PlayerModerationControllerTest extends ClientApiIntegrationTestCase
{
    protected function tearDown(): void
    {
        ServerPlayer::query()->delete();

        parent::tearDown();
    }

    private function seedPlayer(Server $server, string $name = 'Steve'): void
    {
        $player = new ServerPlayer();
        $player->forceFill([
            'server_id' => $server->id,
            'name' => $name,
            'status' => ServerPlayer::STATUS_ONLINE,
            'last_seen_at' => now(),
        ])->save();
    }

    private function expectCommand(Server $server, string $command): void
    {
        $mock = $this->mock(DaemonCommandRepository::class);
        $mock->expects('setServer')
            ->with(Mockery::on(fn (Server $value) => $value->is($server)))
            ->andReturnSelf();
        $mock->expects('send')->with($command)->andReturn(new GuzzleResponse());
    }

    public function testAMessageIsSentAsATellCommand(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_PLAYERS_MODERATE]);
        $this->seedPlayer($server);
        $this->expectCommand($server, 'tell Steve please stop');

        $this->actingAs($user)
            ->postJson("/api/client/servers/$server->uuid/players/Steve/action", [
                'action' => 'message',
                'text' => 'please stop',
            ])
            ->assertStatus(Response::HTTP_NO_CONTENT);
    }

    public function testAKickIsSentWithItsReason(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_PLAYERS_MODERATE]);
        $this->seedPlayer($server);
        $this->expectCommand($server, 'kick Steve griefing');

        $this->actingAs($user)
            ->postJson("/api/client/servers/$server->uuid/players/Steve/action", [
                'action' => 'kick',
                'text' => 'griefing',
            ])
            ->assertStatus(Response::HTTP_NO_CONTENT);
    }

    public function testABanWithoutAReasonIsStillSent(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_PLAYERS_MODERATE]);
        $this->seedPlayer($server);
        $this->expectCommand($server, 'ban Steve');

        $this->actingAs($user)
            ->postJson("/api/client/servers/$server->uuid/players/Steve/action", [
                'action' => 'ban',
            ])
            ->assertStatus(Response::HTTP_NO_CONTENT);
    }

    /**
     * A console connection is line oriented, so a newline in the reason is a second command. The
     * daemon mock is deliberately strict: any call at all here means something was sent.
     */
    public function testTextContainingALineBreakIsRejectedWithoutTouchingTheDaemon(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_PLAYERS_MODERATE]);
        $this->seedPlayer($server);

        $mock = $this->mock(DaemonCommandRepository::class);
        $mock->shouldNotReceive('setServer');
        $mock->shouldNotReceive('send');

        $this->actingAs($user)
            ->postJson("/api/client/servers/$server->uuid/players/Steve/action", [
                'action' => 'message',
                'text' => "hello\nop attacker",
            ])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testAnUnknownActionIsRejected(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_PLAYERS_MODERATE]);
        $this->seedPlayer($server);

        $mock = $this->mock(DaemonCommandRepository::class);
        $mock->shouldNotReceive('send');

        $this->actingAs($user)
            ->postJson("/api/client/servers/$server->uuid/players/Steve/action", [
                'action' => 'op',
            ])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Without this the endpoint would be `ban <anyone>` rather than `ban <someone who plays here>`.
     */
    public function testAPlayerNotOnThisServersRosterIsRejected(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_PLAYERS_MODERATE]);
        $this->seedPlayer($server, 'Steve');

        $mock = $this->mock(DaemonCommandRepository::class);
        $mock->shouldNotReceive('send');

        $this->actingAs($user)
            ->postJson("/api/client/servers/$server->uuid/players/Alex/action", [
                'action' => 'ban',
            ])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Reading the roster is a far milder thing than acting on it, so `players.read` must not carry
     * moderation with it.
     */
    public function testReadPermissionAloneCannotModerate(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_PLAYERS_READ]);
        $this->seedPlayer($server);

        $this->actingAs($user)
            ->postJson("/api/client/servers/$server->uuid/players/Steve/action", [
                'action' => 'kick',
            ])
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testAnOfflineServerReturnsAClearError(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_PLAYERS_MODERATE]);
        $this->seedPlayer($server);

        $mock = $this->mock(DaemonCommandRepository::class);
        $mock->expects('setServer->send')->andThrows(
            new DaemonConnectionException(
                new BadResponseException('', new Request('GET', 'test'), new GuzzleResponse(Response::HTTP_BAD_GATEWAY))
            )
        );

        $this->actingAs($user)
            ->postJson("/api/client/servers/$server->uuid/players/Steve/action", [
                'action' => 'kick',
            ])
            ->assertStatus(Response::HTTP_BAD_GATEWAY)
            ->assertJsonPath('errors.0.detail', 'Server must be online to act on players.');
    }
}
