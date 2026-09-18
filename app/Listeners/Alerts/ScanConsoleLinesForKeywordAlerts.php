<?php

namespace Pterodactyl\Listeners\Alerts;

use Pterodactyl\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Pterodactyl\Notifications\KeywordAlertTriggered;
use Pterodactyl\Services\Alerts\KeywordAlertDeduplicator;
use Pterodactyl\Events\ConsoleArchive\ConsoleLinesCaptured;
use Pterodactyl\Services\Alerts\KeywordAlertMatchingService;

/**
 * Scans every captured console/chat line for configured keyword alert rules.
 *
 * ================================================================================================
 * THIS LISTENER RUNS SYNCHRONOUSLY, INLINE, ON THE CONSOLE-ARCHIVE INGESTION DAEMON'S OWN PROCESS.
 * ================================================================================================
 * ConsoleLinesCaptured is dispatched synchronously immediately after each batch INSERT, once per
 * server, and this listener is NOT queued (deliberately — see below). That means:
 *   - Everything in handle() must be fast. Matching itself is a cached in-memory rule scan (see
 *     KeywordAlertMatchingService) plus one small DB write per NEW incident (KeywordAlertRule
 *     lookups are cached; KeywordAlert writes only happen on genuine new/repeated matches, not
 *     per line) — this comfortably keeps up with this fleet's volume (~13 servers, tens of
 *     messages/sec at peak, see plan-keyword-alerts.md §3).
 *   - handle() must NEVER throw. A single bad rule, a DB hiccup, or anything else going wrong
 *     here must degrade to "this batch's alerts were not scanned", not "console-archive ingestion
 *     for this server (and, if the daemon isn't sharded, every other server) stalls or crashes".
 *     The whole body is wrapped in try/catch(\Throwable) for exactly this reason.
 *   - Actual notification delivery (mail) is handed to Notification::send(), which is safe to
 *     call inline here ONLY because KeywordAlertTriggered implements ShouldQueue — that call just
 *     inserts a queue job, it does not itself send mail or make a network call. If a future
 *     channel is added that is NOT queueable, do not call it directly from here.
 *
 * This listener is intentionally NOT itself `ShouldQueue`. Laravel would happily queue a listener
 * regardless of whether the firing event is queued, which would fully decouple matching from the
 * ingestion loop — but it would also mean a burst of batches queues a burst of scan jobs with no
 * back-pressure from the daemon's own batching/rate limiting, and matching latency would then
 * depend on general queue worker load rather than being bounded by (fast, cached) in-process work.
 * Keeping the scan inline keeps latency predictable and keeps the one truly slow part (mail
 * delivery) on the queue where it belongs.
 */
class ScanConsoleLinesForKeywordAlerts
{
    public function __construct(
        private readonly KeywordAlertMatchingService $matcher,
        private readonly KeywordAlertDeduplicator $deduplicator,
    ) {
    }

    public function handle(ConsoleLinesCaptured $event): void
    {
        try {
            $this->scan($event);
        } catch (\Throwable $exception) {
            // Must not throw — see class docblock. Losing scan coverage for one batch is an
            // acceptable degradation; stalling console-archive ingestion for every server is not.
            Log::error('keyword_alerts: listener failed while scanning a batch, alerts for this batch were skipped.', [
                'server_id' => $event->server->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function scan(ConsoleLinesCaptured $event): void
    {
        foreach ($event->lines as $line) {
            $matches = $this->matcher->match($line->line);

            foreach ($matches as $match) {
                $result = $this->deduplicator->record(
                    $match->rule,
                    $event->server,
                    $match,
                    $line->line,
                    $line->source,
                    $line->player,
                    $line->loggedAt,
                );

                if ($result['isNewIncident']) {
                    $this->notify($result['alert']);
                }
            }
        }
    }

    private function notify(\Pterodactyl\Models\KeywordAlert $alert): void
    {
        // Recipient targeting v1: every root admin. There is no "on-duty staff" or per-user
        // opt-in concept anywhere in this schema yet (see plan-keyword-alerts.md's "Staff
        // targeting" section) — defaulting to "every root admin gets every alert" is the
        // deliberately over-inclusive choice for a safeguarding tool (false positives are
        // preferable to a missed alert), at the cost of potentially paging people who are not
        // actually the on-duty supervisor. A future iteration should add a per-user
        // opt-in/severity-threshold column and target that instead — flagged in the PR report.
        $recipients = User::query()->where('root_admin', true)->get();

        if ($recipients->isEmpty()) {
            Log::warning('keyword_alerts: alert triggered but there are no root admin users to notify.', [
                'alert_id' => $alert->id,
            ]);

            return;
        }

        // Notification::send() with a queued Notification only enqueues jobs here; it does not
        // itself perform I/O. Safe to call inline. See class docblock.
        Notification::send($recipients, new KeywordAlertTriggered($alert));

        $alert->forceFill(['notified_at' => now()])->save();
    }
}
