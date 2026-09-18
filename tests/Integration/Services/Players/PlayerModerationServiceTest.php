<?php

namespace Pterodactyl\Tests\Integration\Services\Players;

use Mockery;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerPlayer;
use Pterodactyl\Services\Players\PlayerModerationService;
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

        parent::tearDown();
    }

    private function service(): PlayerModerationService
    {
        return new PlayerModerationService($this->app->make(DaemonCommandRepository::class));
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
        $server = $this->createServerModel();
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
        $server = $this->createServerModel();
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
        $server = $this->createServerModel();
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
        $server = $this->createServerModel();
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
        $server = $this->createServerModel();
        $other = $this->createServerModel();
        $this->seedPlayer($other, 'Steve');

        $this->expectException(\InvalidArgumentException::class);

        $this->service()->buildCommand($server, 'kick', 'Steve', null);
    }

    public function testAMessageWithoutTextIsRefused(): void
    {
        $server = $this->createServerModel();
        $this->seedPlayer($server);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A message is required.');

        $this->service()->buildCommand($server, 'message', 'Steve', null);
    }

    public function testAnUnknownActionIsRefused(): void
    {
        $server = $this->createServerModel();
        $this->seedPlayer($server);

        $this->expectException(\InvalidArgumentException::class);

        $this->service()->buildCommand($server, 'op', 'Steve', null);
    }

    public function testSendIssuesTheCommandToTheDaemon(): void
    {
        $server = $this->createServerModel();
        $this->seedPlayer($server);

        $repository = Mockery::mock(DaemonCommandRepository::class);
        $repository->shouldReceive('setServer')->once()->with(Mockery::on(
            fn (Server $passed) => $passed->id === $server->id
        ))->andReturnSelf();
        $repository->shouldReceive('send')->once()->with('kick Steve rude');

        $service = new PlayerModerationService($repository);

        $this->assertSame('kick Steve rude', $service->send($server, 'kick', 'Steve', 'rude'));
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
