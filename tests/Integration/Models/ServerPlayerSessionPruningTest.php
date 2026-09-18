<?php

namespace Pterodactyl\Tests\Integration\Models;

use Pterodactyl\Models\ServerPlayer;
use Pterodactyl\Models\ServerPlayerSession;
use Pterodactyl\Tests\Integration\IntegrationTestCase;

/**
 * The session history is the safeguarding record an operator pulls up after something has
 * already happened, so its retention window is its own config key and defaults to something
 * much longer than the roster's. Everything asserted below is about that window applying
 * correctly and, crucially, never reaching back into the roster it is not responsible for.
 */
class ServerPlayerSessionPruningTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        ServerPlayer::query()->delete();
        ServerPlayerSession::query()->delete();

        parent::tearDown();
    }

    private function createSession(int $serverId, string $name, string $event, string $occurredAt): ServerPlayerSession
    {
        $session = new ServerPlayerSession();
        $session->forceFill([
            'server_id' => $serverId,
            'name' => $name,
            'event' => $event,
            'occurred_at' => $occurredAt,
        ])->save();

        return $session;
    }

    public function testEventsPastTheRetentionWindowArePruned(): void
    {
        config()->set('players.session_prune_days', 90);
        $server = $this->createServerModel();

        $stale = $this->createSession($server->id, 'Stale', ServerPlayerSession::EVENT_JOIN, now()->subDays(91)->toDateTimeString());
        $recent = $this->createSession($server->id, 'Recent', ServerPlayerSession::EVENT_JOIN, now()->subDays(89)->toDateTimeString());

        $this->assertSame(['Stale'], (new ServerPlayerSession())->prunable()->pluck('name')->all());

        $this->artisan('model:prune', ['--model' => [ServerPlayerSession::class]])->assertExitCode(0);

        $this->assertNull(ServerPlayerSession::query()->find($stale->id));
        $this->assertNotNull(ServerPlayerSession::query()->find($recent->id));
    }

    /**
     * Pruning history is a separate concern from pruning the roster — deleting a player's
     * join/leave events must not touch their current roster row, which is what
     * ServerPlayer::prunable() alone is responsible for dropping.
     */
    public function testPruningHistoryLeavesTheRosterIntact(): void
    {
        config()->set('players.session_prune_days', 90);
        $server = $this->createServerModel();

        $player = new ServerPlayer();
        $player->forceFill([
            'server_id' => $server->id,
            'name' => 'Stale',
            'status' => ServerPlayer::STATUS_OFFLINE,
            'last_seen_at' => now()->subDays(91)->toDateTimeString(),
        ])->save();

        $this->createSession($server->id, 'Stale', ServerPlayerSession::EVENT_JOIN, now()->subDays(91)->toDateTimeString());

        $this->artisan('model:prune', ['--model' => [ServerPlayerSession::class]])->assertExitCode(0);

        $this->assertSame(0, ServerPlayerSession::query()->where('name', 'Stale')->count());
        $this->assertSame(1, ServerPlayer::query()->where('name', 'Stale')->count());
    }

    /**
     * Setting the window to zero is how an operator turns retention off; it must mean "keep
     * everything", not "prune everything", since the latter silently empties the safeguarding
     * record.
     */
    public function testAZeroRetentionWindowPrunesNothing(): void
    {
        config()->set('players.session_prune_days', 0);
        $server = $this->createServerModel();
        $ancient = $this->createSession($server->id, 'Ancient', ServerPlayerSession::EVENT_JOIN, now()->subYears(2)->toDateTimeString());

        $this->assertSame(0, (new ServerPlayerSession())->prunable()->count());

        $this->artisan('model:prune', ['--model' => [ServerPlayerSession::class]])->assertExitCode(0);

        $this->assertNotNull(ServerPlayerSession::query()->find($ancient->id));
    }
}
