<?php

namespace Pterodactyl\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;

/**
 * \Pterodactyl\Models\ServerConsoleArchive.
 *
 * An append-only, best-effort archive of a server's Wings console/log output, captured
 * continuously and server-side by the `console-archive:consume` daemon (regardless of whether
 * anyone has the console tab open). For the stock Minecraft/Paper/Bungee eggs this line is chat,
 * since chat is interleaved directly into console output.
 *
 * This is a safeguarding tool over children's in-game speech, not a general-purpose log viewer:
 * treat read access accordingly (see Permission::ACTION_ARCHIVE_READ and ServerPolicy).
 *
 * @property int $id
 * @property int $server_id
 * @property \Carbon\Carbon $logged_at
 * @property string $line
 * @property string $source
 * @property string|null $player
 * @property \Pterodactyl\Models\Server $server
 *
 * @method static Builder|ServerConsoleArchive forServer(\Pterodactyl\Models\Server $server)
 * @method static Builder|ServerConsoleArchive query()
 */
class ServerConsoleArchive extends Model
{
    use MassPrunable;

    public const RESOURCE_NAME = 'console_archive_entry';

    public const SOURCE_CONSOLE = 'console';
    public const SOURCE_CHAT = 'chat';

    public $timestamps = false;

    protected $table = 'server_console_archives';

    protected $guarded = ['id'];

    protected $casts = [
        'server_id' => 'integer',
        'logged_at' => 'datetime',
    ];

    public static array $validationRules = [
        'server_id' => 'required|integer|exists:servers,id',
        'logged_at' => 'required|date',
        'line' => 'required|string',
        'source' => 'required|string|in:console,chat',
        'player' => 'nullable|string|max:64',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Pterodactyl\Models\Server, $this>
     */
    public function server(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function scopeForServer(Builder $builder, Server $server): Builder
    {
        return $builder->where('server_id', $server->id);
    }

    /**
     * Builds plain insert rows (server_id, logged_at, line, source, player) from a batch of
     * CapturedConsoleLine value objects, suitable for a single multi-row `insert()` call. Kept as
     * a static helper (rather than inline in the ingestion daemon) so the row shape can be unit
     * tested without a database connection.
     *
     * @param \Pterodactyl\Services\ConsoleArchive\CapturedConsoleLine[] $lines
     *
     * @return array<int, array{server_id: int, logged_at: string, line: string, source: string, player: string|null}>
     */
    public static function insertRowsFrom(array $lines): array
    {
        return array_map(
            static fn (\Pterodactyl\Services\ConsoleArchive\CapturedConsoleLine $line) => [
                'server_id' => $line->serverId,
                'logged_at' => $line->loggedAt->format('Y-m-d H:i:s'),
                'line' => $line->line,
                'source' => $line->source,
                'player' => $line->player,
            ],
            $lines
        );
    }

    /**
     * Returns models to be pruned. Mirrors ActivityLog::prunable(), driven by
     * config/console_archive.php's `prune_days` instead of config/activity.php's.
     *
     * @see https://laravel.com/docs/10.x/eloquent#pruning-models
     */
    public function prunable()
    {
        if (is_null(config('console_archive.prune_days'))) {
            throw new \LogicException('Cannot prune console archives: no "prune_days" configuration value is set.');
        }

        return static::where('logged_at', '<=', Carbon::now()->subDays(config('console_archive.prune_days')));
    }
}
