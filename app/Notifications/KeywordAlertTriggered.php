<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Pterodactyl\Models\KeywordAlert;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Notifies a staff member that a keyword alert rule fired. Always `ShouldQueue`: delivery
 * (mail transport, potentially a slow SMTP round-trip) must never run inline on the
 * console-archive ingestion daemon's process — see ScanConsoleLinesForKeywordAlerts's docblock.
 * The KeywordAlert row itself (the primary, always-available record) is written synchronously
 * before this notification is ever dispatched, so a failed/delayed send never loses the alert —
 * it only delays a staff member's inbox ping. Failed sends land in Laravel's normal failed-jobs
 * handling (retried per the queue connection's retry policy, then logged to `failed_jobs`); they
 * do not retry indefinitely and do not block subsequent alerts.
 */
class KeywordAlertTriggered extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly KeywordAlert $alert)
    {
    }

    /**
     * @return string[]
     */
    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(): MailMessage
    {
        $alert = $this->alert;
        // rule_id/server_id both cascadeOnDelete, so these are only absent if the relation hasn't
        // been loaded and the underlying row is gone in the same request race — fall back rather
        // than let toMail() throw while a queued job is rendering the notification.
        $server = $alert->server;
        $rule = $alert->rule;

        $subject = sprintf('[%s] Keyword alert: %s', strtoupper($alert->severity), $rule->label ?? 'Unknown rule');

        $message = (new MailMessage())
            ->subject($subject)
            ->greeting('A keyword alert rule was triggered.')
            ->line('Rule: ' . ($rule->label ?? 'Unknown rule'))
            ->line('Server: ' . ($server->name ?? ('#' . $alert->server_id)))
            ->line('Severity: ' . strtoupper($alert->severity))
            ->line('Matched text: "' . $alert->matched_text . '"');

        if ($alert->player) {
            $message->line('Player: ' . $alert->player);
        }

        if ($alert->occurrence_count > 1) {
            $message->line("This has occurred {$alert->occurrence_count} times in quick succession; only the first triggered this email.");
        }

        return $message
            ->line('Full line: ' . $alert->line)
            ->action('Review in admin panel', url('/admin/keyword-alerts'));
    }
}
