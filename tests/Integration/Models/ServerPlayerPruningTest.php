<?php

namespace Pterodactyl\Tests\Integration\Models;

use Pterodactyl\Models\ServerPlayer;
use Pterodactyl\Models\ServerPlayerSession;
use Pterodactyl\Tests\Integration\IntegrationTestCase;

/**
 * The roster is a record of who has played here, and it is pruned on a schedule rather than kept
 * forever. Everything asserted below is about not deleting the wrong rows: an online player and a
 * player's session history are both things an operator would be surprised to lose.
 */
class ServerPlayerPruningTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        ServerPlayer::query()->delete();
        ServerPlayerSession::query()->delete();

        parent::tearDown();
    }

    private function player(int $serverId, string $name, string $status, ?string $lastSeen): ServerPlayer
    {
        $player = new ServerPlayer();
        $player->forceFill([
            'server_id' => $serverId,
            'name' => $name,
            'status' => $status,
            'last_seen_at' => $lastSeen,
        ])->save();

        return $player;
    }

    public function testOnlyOfflinePlayersPastTheRetentionWindowArePruned(): void
    {
        config()->set('players.prune_days', 7);
        $server = $this->createServerModel();

        $stale = $this->player($server->id, 'Stale', ServerPlayer::STATUS_OFFLINE, now()->subDays(8)->toDateTimeString());
        $recent = $this->player($server->id, 'Recent', ServerPlayer::STATUS_OFFLINE, now()->subDays(6)->toDateTimeString());
        // An online player has no business being pruned no matter how long ago the roster last saw
        // a line from them — a quiet, long-running session is not an absence.
        $online = $this->player($server->id, 'Online', ServerPlayer::STATUS_ONLINE, now()->subDays(30)->toDateTimeString());
        // Never seen: nothing to measure the window against, so it cannot be past it.
        $unseen = $this->player($server->id, 'Unseen', ServerPlayer::STATUS_OFFLINE, null);

        $this->assertSame(['Stale'], (new ServerPlayer())->prunable()->pluck('name')->all());

        $this->artisan('model:prune', ['--model' => [ServerPlayer::class]])->assertExitCode(0);

        $this->assertNull(ServerPlayer::query()->find($stale->id));
        $this->assertNotNull(ServerPlayer::query()->find($recent->id));
        $this->assertNotNull(ServerPlayer::query()->find($online->id));
        $this->assertNotNull(ServerPlayer::query()->find($unseen->id));
    }

    /**
     * Retention applies to the roster listing only. The session history is a separate, append-only
     * record — an operator looking up what happened three weeks ago must still find it.
     */
    public function testPruningTheRosterLeavesSessionHistoryIntact(): void
    {
        config()->set('players.prune_days', 7);
        $server = $this->createServerModel();
        $this->player($server->id, 'Stale', ServerPlayer::STATUS_OFFLINE, now()->subDays(30)->toDateTimeString());

        ServerPlayerSession::query()->create([
            'server_id' => $server->id,
            'name' => 'Stale',
            'event' => 'join',
            'occurred_at' => now()->subDays(30),
        ]);

        $this->artisan('model:prune', ['--model' => [ServerPlayer::class]])->assertExitCode(0);

        $this->assertSame(0, ServerPlayer::query()->where('name', 'Stale')->count());
        $this->assertSame(1, ServerPlayerSession::query()->where('name', 'Stale')->count());
    }

    /**
     * Setting the window to zero is how an operator turns retention off; it must mean "keep
     * everything", not "prune everything", since the latter silently empties the roster.
     */
    public function testAZeroRetentionWindowPrunesNothing(): void
    {
        config()->set('players.prune_days', 0);
        $server = $this->createServerModel();
        $this->player($server->id, 'Ancient', ServerPlayer::STATUS_OFFLINE, now()->subYears(2)->toDateTimeString());

        $this->assertSame(0, (new ServerPlayer())->prunable()->count());

        $this->artisan('model:prune', ['--model' => [ServerPlayer::class]])->assertExitCode(0);

        $this->assertSame(1, ServerPlayer::query()->where('name', 'Ancient')->count());
    }
}
