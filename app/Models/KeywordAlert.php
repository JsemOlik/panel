<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A triggered keyword alert: one row per (rule, server) pair within a dedup window — see
 * \Pterodactyl\Services\Alerts\KeywordAlertDeduplicator. Repeated matches of the same rule on the
 * same server inside the window bump `occurrence_count` and `last_seen_at` on the existing row
 * rather than creating a new one, so one keyword repeated hundreds of times in a raid/spam burst
 * produces one alert, not hundreds.
 *
 * This table holds flagged children's chat. Treat it like ServerConsoleArchive
 * (app/Models/ServerConsoleArchive.php): restrict read access, and prune it — see prunable().
 *
 * @property int $id
 * @property int $rule_id
 * @property int $server_id
 * @property string|null $player
 * @property string $source
 * @property string $line
 * @property string $matched_text
 * @property string $severity
 * @property string $status
 * @property int $occurrence_count
 * @property \Carbon\Carbon $first_seen_at
 * @property \Carbon\Carbon $last_seen_at
 * @property \Carbon\Carbon|null $notified_at
 * @property int|null $resolved_by
 * @property \Carbon\Carbon|null $resolved_at
 * @property \Pterodactyl\Models\KeywordAlertRule $rule
 * @property \Pterodactyl\Models\Server $server
 */
class KeywordAlert extends Model
{
    use MassPrunable;

    public const RESOURCE_NAME = 'keyword_alert';

    public const STATUS_OPEN = 'open';
    public const STATUS_RESOLVED = 'resolved';

    protected $table = 'keyword_alerts';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'rule_id' => 'integer',
        'server_id' => 'integer',
        'occurrence_count' => 'integer',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'notified_at' => 'datetime',
        'resolved_by' => 'integer',
        'resolved_at' => 'datetime',
    ];

    public static array $validationRules = [
        'rule_id' => 'required|integer|exists:keyword_alert_rules,id',
        'server_id' => 'required|integer|exists:servers,id',
        'player' => 'nullable|string|max:191',
        'source' => 'required|string|max:16',
        'line' => 'required|string',
        'matched_text' => 'required|string|max:191',
        'severity' => 'required|string|in:info,warning,critical',
        'status' => 'required|string|in:open,resolved',
        'occurrence_count' => 'required|integer|min:1',
        'first_seen_at' => 'required|date',
        'last_seen_at' => 'required|date',
    ];

    /**
     * @return BelongsTo<KeywordAlertRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(KeywordAlertRule::class, 'rule_id');
    }

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Mirrors ServerConsoleArchive::prunable() — same reasoning (this is children's chat, don't
     * keep it forever), configurable independently via config('keyword_alerts.prune_days').
     */
    public function prunable()
    {
        if (is_null(config('keyword_alerts.prune_days'))) {
            throw new \LogicException('Cannot prune keyword alerts: no "prune_days" configuration value is set.');
        }

        return static::where('created_at', '<=', now()->subDays(config('keyword_alerts.prune_days')));
    }
}
