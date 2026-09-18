<?php

namespace Pterodactyl\Services\Alerts;

use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Models\KeywordAlert;
use Pterodactyl\Models\KeywordAlertRule;

/**
 * Decides whether a rule match should create a brand new KeywordAlert (and trigger a
 * notification) or fold into the most recent still-open one for the same (rule, server) pair.
 *
 * Why this exists: a single spammy line ("idiot idiot idiot idiot...") or a plugin stuck printing
 * the same warning hundreds of times a second would otherwise create hundreds of DB rows and fire
 * hundreds of mail notifications for the exact same underlying incident — which is both noise and
 * the specific failure mode ("staff learn to ignore the channel") that makes a safeguarding alert
 * tool useless. See config('keyword_alerts.dedup_window_seconds').
 *
 * Dedup key is (rule, server) only — deliberately NOT per-player. Two different children both
 * triggering the same slur rule within the window are two different safeguarding situations and
 * must both notify; two different players both triggering "Can't keep up" on the same server are
 * the same operational incident and should not double-page. Per-rule-per-server is the coarser,
 * safer default for a mixed rule set — it trades a small amount of extra dedup on busy chat rules
 * against never silently swallowing a second child's flagged message.
 */
class KeywordAlertDeduplicator
{
    /**
     * Records a match, returning the KeywordAlert row it should be represented by, and whether
     * this call is the one that should trigger a notification (true only the first time within
     * the window for this rule+server pair).
     *
     * @return array{alert: KeywordAlert, isNewIncident: bool}
     */
    public function record(KeywordAlertRule $rule, Server $server, KeywordAlertMatch $match, string $line, string $source, ?string $player, \DateTimeImmutable $loggedAt): array
    {
        $cacheKey = sprintf('keyword_alert_dedup:%d:%d', $rule->id, $server->id);
        $window = (int) config('keyword_alerts.dedup_window_seconds', 600);

        // Cache::add() is atomic: only the first caller within the TTL window gets `true`. That
        // makes this safe even if two ingestion batches for the same server raced each other,
        // without needing a DB-level lock.
        $isNewIncident = Cache::add($cacheKey, true, $window);

        if ($isNewIncident) {
            $alert = KeywordAlert::query()->create([
                'rule_id' => $rule->id,
                'server_id' => $server->id,
                'player' => $player,
                'source' => $source,
                'line' => $line,
                'matched_text' => $match->matchedText,
                'severity' => $rule->severity,
                'status' => KeywordAlert::STATUS_OPEN,
                'occurrence_count' => 1,
                'first_seen_at' => $loggedAt,
                'last_seen_at' => $loggedAt,
            ]);

            // Remember which row to bump for the rest of the window.
            Cache::put($cacheKey . ':alert_id', $alert->id, $window);

            return ['alert' => $alert, 'isNewIncident' => true];
        }

        $alertId = Cache::get($cacheKey . ':alert_id');
        $alert = $alertId ? KeywordAlert::find($alertId) : null;

        // Cache eviction race (e.g. the alert_id sub-key expired slightly before the dedup key,
        // or the cache driver dropped it) — fall back to the most recent alert for this pair
        // rather than silently dropping the occurrence.
        $alert ??= KeywordAlert::query()
            ->where('rule_id', $rule->id)
            ->where('server_id', $server->id)
            ->orderByDesc('id')
            ->first();

        if (!$alert) {
            // Genuinely nothing to bump (shouldn't happen, but fail open into a new row rather
            // than silently losing a flagged message).
            $alert = KeywordAlert::query()->create([
                'rule_id' => $rule->id,
                'server_id' => $server->id,
                'player' => $player,
                'source' => $source,
                'line' => $line,
                'matched_text' => $match->matchedText,
                'severity' => $rule->severity,
                'status' => KeywordAlert::STATUS_OPEN,
                'occurrence_count' => 1,
                'first_seen_at' => $loggedAt,
                'last_seen_at' => $loggedAt,
            ]);

            return ['alert' => $alert, 'isNewIncident' => true];
        }

        $alert->increment('occurrence_count');
        $alert->forceFill(['last_seen_at' => $loggedAt])->save();

        return ['alert' => $alert, 'isNewIncident' => false];
    }
}
