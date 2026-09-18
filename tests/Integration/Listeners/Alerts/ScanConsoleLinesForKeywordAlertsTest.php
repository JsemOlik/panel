<?php

namespace Pterodactyl\Tests\Integration\Listeners\Alerts;

use Carbon\CarbonImmutable;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\KeywordAlert;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Models\KeywordAlertRule;
use Illuminate\Support\Facades\Notification;
use Pterodactyl\Events\ConsoleArchive\ConsoleLinesCaptured;
use Pterodactyl\Notifications\KeywordAlertTriggered;
use Pterodactyl\Services\ConsoleArchive\CapturedConsoleLine;
use Pterodactyl\Listeners\Alerts\ScanConsoleLinesForKeywordAlerts;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

/**
 * Exercises the listener end to end against synthetic console lines — no live Wings, no real
 * mail delivery (Notification::fake()). Covers: matching creates an alert row, dedup folds
 * repeats within the window into one row/one notification, and a throwing rule/match does not
 * propagate out of handle() (the hard requirement given this runs inline on the ingestion
 * daemon's process — see the listener's class docblock).
 */
class ScanConsoleLinesForKeywordAlertsTest extends ClientApiIntegrationTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // Dedup uses the cache; make sure no state leaks between tests.
        Cache::flush();
    }

    protected function tearDown(): void
    {
        KeywordAlert::query()->delete();
        KeywordAlertRule::query()->delete();
        Cache::flush();

        parent::tearDown();
    }

    private function line(Server $server, string $text, ?string $player = null, string $source = 'chat'): CapturedConsoleLine
    {
        return new CapturedConsoleLine($server->id, CarbonImmutable::now()->toDateTimeImmutable(), $text, $source, $player);
    }

    public function testMatchingLineCreatesAnAlertAndNotifiesRootAdmins(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $server = $this->createServerModel();
        $rule = KeywordAlertRule::query()->create([
            'uuid' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
            'label' => 'Self-harm language',
            'phrase' => 'kys',
            'match_type' => KeywordAlertRule::MATCH_WORD,
            'severity' => KeywordAlertRule::SEVERITY_CRITICAL,
            'case_sensitive' => false,
            'enabled' => true,
        ]);

        $listener = $this->app->make(ScanConsoleLinesForKeywordAlerts::class);
        $listener->handle(new ConsoleLinesCaptured($server, [
            $this->line($server, '<Steve> kys', 'Steve'),
        ]));

        $this->assertDatabaseCount('keyword_alerts', 1);
        $alert = KeywordAlert::query()->first();
        $this->assertSame($rule->id, $alert->rule_id);
        $this->assertSame($server->id, $alert->server_id);
        $this->assertSame('Steve', $alert->player);
        $this->assertSame(1, $alert->occurrence_count);
        $this->assertSame(KeywordAlert::STATUS_OPEN, $alert->status);

        Notification::assertSentTo($admin, KeywordAlertTriggered::class, function (KeywordAlertTriggered $notification) use ($alert) {
            return $notification->alert->id === $alert->id;
        });
    }

    public function testRepeatedMatchesWithinTheDedupWindowFoldIntoOneAlertAndOneNotification(): void
    {
        Notification::fake();
        config(['keyword_alerts.dedup_window_seconds' => 600]);

        $admin = User::factory()->admin()->create();
        $server = $this->createServerModel();
        KeywordAlertRule::query()->create([
            'uuid' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
            'label' => 'Spam term',
            'phrase' => 'spam',
            'match_type' => KeywordAlertRule::MATCH_WORD,
            'severity' => KeywordAlertRule::SEVERITY_INFO,
            'case_sensitive' => false,
            'enabled' => true,
        ]);

        $listener = $this->app->make(ScanConsoleLinesForKeywordAlerts::class);
        $listener->handle(new ConsoleLinesCaptured($server, [
            $this->line($server, 'spam', 'PlayerA'),
            $this->line($server, 'spam', 'PlayerA'),
            $this->line($server, 'spam', 'PlayerA'),
        ]));

        $this->assertDatabaseCount('keyword_alerts', 1);
        $alert = KeywordAlert::query()->first();
        $this->assertSame(3, $alert->occurrence_count);

        Notification::assertSentToTimes($admin, KeywordAlertTriggered::class, 1);
    }

    public function testMatchAfterTheDedupWindowExpiresCreatesANewAlert(): void
    {
        Notification::fake();

        User::factory()->admin()->create();
        $server = $this->createServerModel();
        KeywordAlertRule::query()->create([
            'uuid' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
            'label' => 'Spam term',
            'phrase' => 'spam',
            'match_type' => KeywordAlertRule::MATCH_WORD,
            'severity' => KeywordAlertRule::SEVERITY_INFO,
            'case_sensitive' => false,
            'enabled' => true,
        ]);

        $listener = $this->app->make(ScanConsoleLinesForKeywordAlerts::class);
        $listener->handle(new ConsoleLinesCaptured($server, [$this->line($server, 'spam')]));

        // Simulate the dedup window having elapsed by clearing the cache directly rather than
        // sleeping in the test.
        Cache::flush();

        $listener->handle(new ConsoleLinesCaptured($server, [$this->line($server, 'spam')]));

        $this->assertDatabaseCount('keyword_alerts', 2);
    }

    public function testDisabledRuleDoesNotMatch(): void
    {
        Notification::fake();

        $server = $this->createServerModel();
        KeywordAlertRule::query()->create([
            'uuid' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
            'label' => 'Disabled rule',
            'phrase' => 'shouldnotmatch',
            'match_type' => KeywordAlertRule::MATCH_WORD,
            'severity' => KeywordAlertRule::SEVERITY_INFO,
            'case_sensitive' => false,
            'enabled' => false,
        ]);

        $listener = $this->app->make(ScanConsoleLinesForKeywordAlerts::class);
        $listener->handle(new ConsoleLinesCaptured($server, [$this->line($server, 'shouldnotmatch')]));

        $this->assertDatabaseCount('keyword_alerts', 0);
    }

    /**
     * The hard requirement: this listener runs inline on the ingestion daemon's process, so it
     * must never let an exception escape handle() — not even when the matching rule set (via a
     * corrupt row, in this simulation a rule with a malformed regex) would otherwise throw.
     */
    public function testAMalformedRuleDoesNotThrowOutOfTheListener(): void
    {
        Notification::fake();

        $server = $this->createServerModel();
        KeywordAlertRule::query()->create([
            'uuid' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
            'label' => 'Bad regex',
            'phrase' => '/(unclosed',
            'match_type' => KeywordAlertRule::MATCH_REGEX,
            'severity' => KeywordAlertRule::SEVERITY_INFO,
            'case_sensitive' => false,
            'enabled' => true,
        ]);

        $listener = $this->app->make(ScanConsoleLinesForKeywordAlerts::class);

        // No exception should propagate.
        $listener->handle(new ConsoleLinesCaptured($server, [$this->line($server, 'anything at all')]));

        $this->assertDatabaseCount('keyword_alerts', 0);
    }
}
