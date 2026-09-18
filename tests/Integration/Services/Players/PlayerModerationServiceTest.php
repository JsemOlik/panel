<?php

namespace Pterodactyl\Tests\Integration\Services\Players;

use Mockery;
use Ramsey\Uuid\Uuid;
use Pterodactyl\Models\Area;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerPlayer;
use Pterodactyl\Services\Players\PlayerModerationService;
use Pterodactyl\Services\Players\PlayerPresenceService;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Pterodactyl\Repositories\Wings\DaemonCommandRepository;

/**
 * The endpoint behind this service grants `players.moderate`, a permission far narrower than
 * `control.console`, the ability to write to the console. These tests exist to keep that
 * narrowness real: if a crafted name or message can smuggle in a second command, the split
 * between the two permissions is decorative.
 */
class PlayerModerationServiceTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        ServerPlayer::query()->delete();
        Area::query()->delete();

        parent::tearDown();
    }

    private function service(): PlayerModerationService
    {
        return new PlayerModerationService(
            $this->app->make(DaemonCommandRepository::class),
            $this->app->make(PlayerPresenceService::class),
        );
    }

    /**
     * createServerModel() defaults to the BungeeCord egg (see
     * CreatesTestModels::getBungeecordEgg), which PlayerPresenceService::isProxy() treats as a
     * proxy — these tests exercise the backend moderation path, so they need a non-proxy egg
     * explicitly. Mirrors TrackPlayerPresenceListenerTest::createBackendServerModel().
     */
    private function createBackendServerModel(array $attributes = []): Server
    {
        $egg = Egg::query()->where('name', 'Paper')->firstOrFail();

        return $this->createServerModel($attributes + ['egg_id' => $egg->id, 'nest_id' => $egg->nest_id]);
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

    public function testBuildsTheExpectedCommandForEachAction(): void
    {
        $server = $this->createBackendServerModel();
        $this->seedPlayer($server);

        $service = $this->service();

        $this->assertSame('tell Steve stop that please', $service->buildCommand($server, 'message', 'Steve', 'stop that please'));
        $this->assertSame('kick Steve being unkind', $service->buildCommand($server, 'kick', 'Steve', 'being unkind'));
        $this->assertSame('ban Steve repeated abuse', $service->buildCommand($server, 'ban', 'Steve', 'repeated abuse'));

        // A reason is optional, and must not leave a trailing space that would reach the console.
        $this->assertSame('kick Steve', $service->buildCommand($server, 'kick', 'Steve', null));
        $this->assertSame('ban Steve', $service->buildCommand($server, 'ban', 'Steve', '   '));
    }

    /**
     * The core of the whole design: a console connection is line oriented, so a newline in the
     * text is a second command. "hello\nop attacker" must never be sendable.
     */
    public function testTextContainingALineBreakIsRefused(): void
    {
        $server = $this->createBackendServerModel();
        $this->seedPlayer($server);

        foreach (["hello\nop attacker", "hello\rop attacker", "a\r\nb"] as $payload) {
            try {
                $this->service()->buildCommand($server, 'message', 'Steve', $payload);
                $this->fail('A line break in the text was accepted: ' . json_encode($payload));
            } catch (\InvalidArgumentException $exception) {
                $this->assertStringContainsString('line breaks', $exception->getMessage());
            }
        }
    }

    public function testAPlayerNameOutsideTheUsernameCharsetIsRefused(): void
    {
        $server = $this->createBackendServerModel();
        $this->seedPlayer($server);

        foreach (["Steve\nop attacker", 'Steve attacker', 'Steve; op attacker', ''] as $name) {
            $this->expectExceptionOnBuild($server, $name);
        }
    }

    /**
     * Restricting the charset is not enough on its own — without this the endpoint would run
     * `ban <any well-formed name>`, which is a wider capability than moderating the players who
     * are actually on this server.
     */
    public function testAPlayerNotOnThisServersRosterIsRefused(): void
    {
        $server = $this->createBackendServerModel();
        $this->seedPlayer($server, 'Steve');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('has not been seen on this server');

        $this->service()->buildCommand($server, 'ban', 'Alex', null);
    }

    /**
     * Rosters are per-server, so a player known on one server must not be actionable on another.
     */
    public function testAPlayerFromAnotherServerIsRefused(): void
    {
        $server = $this->createBackendServerModel();
        $other = $this->createBackendServerModel();
        $this->seedPlayer($other, 'Steve');

        $this->expectException(\InvalidArgumentException::class);

        $this->service()->buildCommand($server, 'kick', 'Steve', null);
    }

    public function testAMessageWithoutTextIsRefused(): void
    {
        $server = $this->createBackendServerModel();
        $this->seedPlayer($server);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A message is required.');

        $this->service()->buildCommand($server, 'message', 'Steve', null);
    }

    public function testAnUnknownActionIsRefused(): void
    {
        $server = $this->createBackendServerModel();
        $this->seedPlayer($server);

        $this->expectException(\InvalidArgumentException::class);

        $this->service()->buildCommand($server, 'op', 'Steve', null);
    }

    public function testSendIssuesTheCommandToTheDaemon(): void
    {
        $server = $this->createBackendServerModel();
        $this->seedPlayer($server);

        $repository = Mockery::mock(DaemonCommandRepository::class);
        $repository->shouldReceive('setServer')->once()->with(Mockery::on(
            fn (Server $passed) => $passed->id === $server->id
        ))->andReturnSelf();
        $repository->shouldReceive('send')->once()->with('kick Steve rude');

        $service = new PlayerModerationService($repository, $this->app->make(PlayerPresenceService::class));

        $this->assertSame(
            ['command' => 'kick Steve rude', 'player' => 'Steve'],
            $service->send($server, 'kick', 'Steve', 'rude'),
        );
    }

    /**
     * server_players uses a case-insensitive collation, so a lookup for "jsemolik" matches the
     * row "JsemOlik". The command sent — and the name returned for the activity log — must use
     * the roster's own casing, not whatever the caller happened to type, otherwise the audit trail
     * records a name that doesn't match the player and an exact-match plugin can't resolve it.
     */
    public function testADifferentlyCasedTargetUsesTheRostersCanonicalCasing(): void
    {
        $server = $this->createBackendServerModel();
        $this->seedPlayer($server, 'JsemOlik');

        $service = $this->service();

        $this->assertSame('kick JsemOlik being rude', $service->buildCommand($server, 'kick', 'jsemolik', 'being rude'));
    }

    /**
     * tell/kick/ban are backend Minecraft commands a proxy console (BungeeCord/Velocity) does not
     * have. Wings accepts any string and reports success, so this has to be refused before ever
     * reaching the daemon rather than trusted to a successful send.
     */
    public function testAProxyServerRefusesEveryAction(): void
    {
        // createServerModel() defaults to the BungeeCord egg, which is exactly the case this
        // guard exists for.
        $server = $this->createServerModel();
        $this->seedPlayer($server);

        foreach (['message', 'kick', 'ban'] as $action) {
            try {
                $this->service()->buildCommand($server, $action, 'Steve', 'text');
                $this->fail("The proxy guard did not refuse the '$action' action.");
            } catch (\InvalidArgumentException $exception) {
                $this->assertStringContainsString('proxy', $exception->getMessage());
            }
        }
    }

    /**
     * The primary signal for "is this a proxy" is Area membership with pivot role
     * Area::ROLE_PROXY (see PlayerPresenceService::isProxy), not just the egg name — a backend
     * egg (Paper) explicitly attached to an area as the proxy must still be refused.
     */
    public function testAServerMarkedAsAreaProxyIsRefusedEvenOnABackendEgg(): void
    {
        $server = $this->createBackendServerModel();
        $this->seedPlayer($server);

        $area = Area::query()->create(['uuid' => Uuid::uuid4()->toString(), 'name' => 'Area']);
        $area->servers()->attach($server->id, ['role' => Area::ROLE_PROXY]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('proxy');

        $this->service()->buildCommand($server->fresh(), 'kick', 'Steve', null);
    }

    /**
     * Mirrors SendPlayerActionRequest's max:256 rule, but exercised directly against the service:
     * per the class docblock, this cap must hold even for a caller that never goes through the
     * request class.
     */
    public function testTextOverTheLengthLimitIsRefused(): void
    {
        $server = $this->createBackendServerModel();
        $this->seedPlayer($server);

        $text = str_repeat('a', PlayerModerationService::TEXT_MAX_LENGTH + 1);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage((string) PlayerModerationService::TEXT_MAX_LENGTH);

        $this->service()->buildCommand($server, 'message', 'Steve', $text);
    }

    private function expectExceptionOnBuild(Server $server, string $name): void
    {
        try {
            $this->service()->buildCommand($server, 'kick', $name, null);
            $this->fail('An invalid player name was accepted: ' . json_encode($name));
        } catch (\InvalidArgumentException $exception) {
            $this->addToAssertionCount(1);
        }
    }
}
