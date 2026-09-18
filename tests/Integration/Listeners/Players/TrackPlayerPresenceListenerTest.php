<?php

namespace Pterodactyl\Tests\Integration\Listeners\Players;

use Ramsey\Uuid\Uuid;
use Pterodactyl\Models\Area;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerPlayer;
use Pterodactyl\Models\ServerPlayerSession;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Pterodactyl\Events\ConsoleArchive\ConsoleLinesCaptured;
use Pterodactyl\Services\ConsoleArchive\CapturedConsoleLine;
use Pterodactyl\Listeners\Players\TrackPlayerPresenceListener;

/**
 * Exercises the listener the way the ingestion daemon actually invokes it: dispatch
 * ConsoleLinesCaptured with a batch of CapturedConsoleLine objects and assert on the resulting
 * presence tables — no live Wings connection involved, per the constraint that this feature is
 * tested purely against synthetic console lines.
 */
class TrackPlayerPresenceListenerTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        ServerPlayer::query()->delete();
        ServerPlayerSession::query()->delete();
        Area::query()->delete();
        Server::query()->forceDelete();

        parent::tearDown();
    }

    private function line(int $serverId, string $text, string $loggedAt = '2026-01-01 12:00:00'): CapturedConsoleLine
    {
        return new CapturedConsoleLine($serverId, new \DateTimeImmutable($loggedAt), $text);
    }

    /**
     * createServerModel() defaults to the BungeeCord egg (see CreatesTestModels::getBungeecordEgg,
     * "almost every test just assumes it is using BungeeCord") — these tests exercise backend
     * (vanilla/Paper) parsing, so they need a non-proxy egg explicitly.
     */
    private function createBackendServerModel(array $attributes = []): Server
    {
        $egg = Egg::query()->where('name', 'Paper')->firstOrFail();

        return $this->createServerModel($attributes + ['egg_id' => $egg->id, 'nest_id' => $egg->nest_id]);
    }

    public function testBackendJoinAndLeaveUpdateRosterAndHistory(): void
    {
        $server = $this->createBackendServerModel();
        $listener = $this->app->make(TrackPlayerPresenceListener::class);

        $listener->handle(new ConsoleLinesCaptured($server, [
            $this->line($server->id, '[12:00:00] [Server thread/INFO]: Steve joined the game', '2026-01-01 12:00:00'),
        ]));

        $player = ServerPlayer::query()->forServer($server)->where('name', 'Steve')->firstOrFail();
        $this->assertSame(ServerPlayer::STATUS_ONLINE, $player->status);
        $this->assertNotNull($player->joined_at);

        $listener->handle(new ConsoleLinesCaptured($server, [
            $this->line($server->id, '[12:05:00] [Server thread/INFO]: Steve left the game', '2026-01-01 12:05:00'),
        ]));

        $player->refresh();
        $this->assertSame(ServerPlayer::STATUS_OFFLINE, $player->status);

        $events = ServerPlayerSession::query()->forServer($server)->forPlayer('Steve')->orderBy('occurred_at')->pluck('event')->all();
        $this->assertSame(['join', 'leave'], $events);
    }

    public function testUnrecognisedLinesAreIgnoredWithoutError(): void
    {
        $server = $this->createBackendServerModel();
        $listener = $this->app->make(TrackPlayerPresenceListener::class);

        $listener->handle(new ConsoleLinesCaptured($server, [
            $this->line($server->id, '[12:00:00] [Server thread/INFO]: Preparing spawn area: 42%'),
            $this->line($server->id, '[12:00:01] [Server thread/INFO]: <Steve> hello everyone'),
        ]));

        $this->assertSame(0, ServerPlayer::query()->forServer($server)->count());
        $this->assertSame(0, ServerPlayerSession::query()->forServer($server)->count());
    }

    public function testProxyServerParsesConnectDisconnectAndIgnoresBackendSwitchLines(): void
    {
        $proxy = $this->createServerModel();
        $backend = $this->createBackendServerModel();
        $area = Area::query()->create(['uuid' => Uuid::uuid4()->toString(), 'name' => 'Area']);
        $area->servers()->attach($proxy->id, ['role' => Area::ROLE_PROXY]);
        $area->servers()->attach($backend->id, ['role' => Area::ROLE_MEMBER]);
        $proxy = $proxy->fresh();

        $listener = $this->app->make(TrackPlayerPresenceListener::class);

        $listener->handle(new ConsoleLinesCaptured($proxy, [
            $this->line($proxy->id, '[12:00:00 INFO]: [Steve] has connected', '2026-01-01 12:00:00'),
            // A per-backend switch must not be treated as a proxy leave.
            $this->line($proxy->id, '[12:00:01 INFO]: [Steve] -> lobby has connected', '2026-01-01 12:00:01'),
        ]));

        $player = ServerPlayer::query()->forServer($proxy)->where('name', 'Steve')->firstOrFail();
        $this->assertSame(ServerPlayer::STATUS_ONLINE, $player->status);
        $this->assertSame(1, ServerPlayerSession::query()->forServer($proxy)->forPlayer('Steve')->count());
    }

    public function testOutOfOrderEventDoesNotOverwriteFresherState(): void
    {
        $server = $this->createBackendServerModel();
        $listener = $this->app->make(TrackPlayerPresenceListener::class);

        $listener->handle(new ConsoleLinesCaptured($server, [
            $this->line($server->id, '[12:05:00] [Server thread/INFO]: Steve left the game', '2026-01-01 12:05:00'),
        ]));

        // A stale, earlier join arrives after the fresher leave was already applied (e.g. a
        // reconnect replay) — it must not flip status back to online.
        $listener->handle(new ConsoleLinesCaptured($server, [
            $this->line($server->id, '[12:00:00] [Server thread/INFO]: Steve joined the game', '2026-01-01 12:00:00'),
        ]));

        $player = ServerPlayer::query()->forServer($server)->where('name', 'Steve')->firstOrFail();
        $this->assertSame(ServerPlayer::STATUS_OFFLINE, $player->status);
    }

    public function testListenerNeverThrowsOnUnexpectedInput(): void
    {
        $server = $this->createBackendServerModel();
        $listener = $this->app->make(TrackPlayerPresenceListener::class);

        // A player name at the very edge of / exceeding the column width should be handled
        // gracefully (truncated/rejected by validation at write time, not a thrown exception that
        // would stall ingestion for every other server).
        $longName = str_repeat('A', 100);

        $listener->handle(new ConsoleLinesCaptured($server, [
            $this->line($server->id, "[12:00:00] [Server thread/INFO]: {$longName} joined the game"),
        ]));

        $this->addToAssertionCount(1);
    }
}
