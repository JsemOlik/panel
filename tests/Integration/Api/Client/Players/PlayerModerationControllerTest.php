<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Players;

use Mockery;
use GuzzleHttp\Psr7\Request;
use Illuminate\Http\Response;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Subuser;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\ServerPlayer;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Pterodactyl\Services\Players\PlayerModerationService;
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

    /**
     * generateTestAccount()/createServerModel() default to the BungeeCord egg (see
     * CreatesTestModels::getBungeecordEgg), which PlayerPresenceService::isProxy() treats as a
     * proxy. These tests exercise the backend moderation path, so they need a subuser on an
     * explicitly non-proxy (Paper) server. Mirrors generateTestAccount()'s own permissions branch.
     *
     * @return array{\Pterodactyl\Models\User, \Pterodactyl\Models\Server}
     */
    private function generateTestAccountForBackendServer(array $permissions): array
    {
        $user = User::factory()->create();

        $egg = Egg::query()->where('name', 'Paper')->firstOrFail();
        $server = $this->createServerModel(['egg_id' => $egg->id, 'nest_id' => $egg->nest_id]);

        Subuser::query()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'permissions' => $permissions,
        ]);

        return [$user, $server];
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
        [$user, $server] = $this->generateTestAccountForBackendServer([Permission::ACTION_PLAYERS_MODERATE]);
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
        [$user, $server] = $this->generateTestAccountForBackendServer([Permission::ACTION_PLAYERS_MODERATE]);
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
        [$user, $server] = $this->generateTestAccountForBackendServer([Permission::ACTION_PLAYERS_MODERATE]);
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
        [$user, $server] = $this->generateTestAccountForBackendServer([Permission::ACTION_PLAYERS_MODERATE]);
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
        [$user, $server] = $this->generateTestAccountForBackendServer([Permission::ACTION_PLAYERS_MODERATE]);
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
        [$user, $server] = $this->generateTestAccountForBackendServer([Permission::ACTION_PLAYERS_MODERATE]);
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
        [$user, $server] = $this->generateTestAccountForBackendServer([Permission::ACTION_PLAYERS_MODERATE]);
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

    /**
     * server_players is case-insensitive, so a request for "jsemolik" matches the roster row
     * "JsemOlik". Both the command sent to the daemon and the activity log must use the roster's
     * own casing — the caller-supplied casing would otherwise reach the console and the audit
     * trail unchanged.
     */
    public function testADifferentlyCasedTargetUsesTheRostersCanonicalCasing(): void
    {
        [$user, $server] = $this->generateTestAccountForBackendServer([Permission::ACTION_PLAYERS_MODERATE]);
        $this->seedPlayer($server, 'JsemOlik');
        $this->expectCommand($server, 'kick JsemOlik rude');

        $this->actingAs($user)
            ->postJson("/api/client/servers/$server->uuid/players/jsemolik/action", [
                'action' => 'kick',
                'text' => 'rude',
            ])
            ->assertStatus(Response::HTTP_NO_CONTENT);

        \Illuminate\Support\Facades\Event::assertDispatched(
            \Pterodactyl\Events\ActivityLogged::class,
            fn (\Pterodactyl\Events\ActivityLogged $e) => $e->model->event === 'server:player.kick'
                && $e->model->properties['player'] === 'JsemOlik'
                && $e->model->properties['command'] === 'kick JsemOlik rude'
        );
    }

    /**
     * tell/kick/ban are backend Minecraft commands a proxy console doesn't have. Wings accepts any
     * string and reports success, so this must be refused as a 422 before the daemon is ever
     * touched rather than reported as a successful action that did nothing.
     */
    public function testAProxyServerRefusesEveryActionWithoutTouchingTheDaemon(): void
    {
        // generateTestAccount() defaults to the BungeeCord egg, which is exactly the case this
        // guard exists for.
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_PLAYERS_MODERATE]);
        $this->seedPlayer($server);

        foreach ([
            ['action' => 'message', 'text' => 'hello'],
            ['action' => 'kick'],
            ['action' => 'ban'],
        ] as $payload) {
            $mock = $this->mock(DaemonCommandRepository::class);
            $mock->shouldNotReceive('setServer');
            $mock->shouldNotReceive('send');

            $this->actingAs($user)
                ->postJson("/api/client/servers/$server->uuid/players/Steve/action", $payload)
                ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Mirrors SendPlayerActionRequest's max:256 rule, kept in sync via
     * PlayerModerationService::TEXT_MAX_LENGTH so the two limits cannot drift apart.
     */
    public function testTextOverTheLengthLimitIsRejectedWithoutTouchingTheDaemon(): void
    {
        [$user, $server] = $this->generateTestAccountForBackendServer([Permission::ACTION_PLAYERS_MODERATE]);
        $this->seedPlayer($server);

        $mock = $this->mock(DaemonCommandRepository::class);
        $mock->shouldNotReceive('setServer');
        $mock->shouldNotReceive('send');

        $this->actingAs($user)
            ->postJson("/api/client/servers/$server->uuid/players/Steve/action", [
                'action' => 'kick',
                'text' => str_repeat('a', PlayerModerationService::TEXT_MAX_LENGTH + 1),
            ])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
