<?php

namespace Pterodactyl\Models;

use Illuminate\Support\Facades\Cache;

/**
 * An admin-configured keyword/phrase rule scanned against every captured console/chat line
 * (see \Pterodactyl\Listeners\Alerts\ScanConsoleLinesForKeywordAlerts). Rules are global — they
 * apply to every server on the panel, not per-server or per-area — because this fleet is small
 * (~13 servers) and a missed safeguarding alert is a worse failure than an admin having to scope
 * a rule they didn't want everywhere (see plan-keyword-alerts.md and the KeywordAlertMatchingService
 * docblock for the full rationale).
 *
 * @property int $id
 * @property string $uuid
 * @property string $label
 * @property string $phrase
 * @property string $match_type
 * @property string $severity
 * @property bool $case_sensitive
 * @property bool $enabled
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class KeywordAlertRule extends Model
{
    public const RESOURCE_NAME = 'keyword_alert_rule';

    public const MATCH_SUBSTRING = 'substring';
    public const MATCH_WORD = 'word';
    public const MATCH_REGEX = 'regex';

    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_CRITICAL = 'critical';

    /**
     * Cache key holding every enabled rule. Matching runs once per captured console line across
     * every server on the panel, so this is re-read extremely often — it must never be a plain
     * per-call query. Invalidated below on every write.
     */
    public const CACHE_KEY = 'keyword_alert_rules.enabled';

    protected $table = 'keyword_alert_rules';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'case_sensitive' => 'boolean',
        'enabled' => 'boolean',
    ];

    public static array $validationRules = [
        'uuid' => 'required|string|size:36',
        'label' => 'required|string|between:1,191',
        'phrase' => 'required|string|max:1000',
        'match_type' => 'required|string|in:substring,word,regex',
        'severity' => 'required|string|in:info,warning,critical',
        'case_sensitive' => 'boolean',
        'enabled' => 'boolean',
        'notes' => 'nullable|string',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * All enabled rules, cached — see the class docblock. Callers must not mutate the returned
     * collection; it is shared across every invocation until the cache expires or is invalidated.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
     */
    public static function enabledCached(): \Illuminate\Database\Eloquent\Collection
    {
        // Cache::remember()'s closure return type loses the "static" template binding PHPStan
        // otherwise infers from self::query()->get() — harmless in practice (this class is never
        // subclassed) and not worth fighting with a broader generic annotation.
        return Cache::remember(self::CACHE_KEY, 300, fn () => self::query()->where('enabled', true)->get()); // @phpstan-ignore-line return.type
    }
}
