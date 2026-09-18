<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * \Pterodactyl\Models\ServerPlayer.
 *
 * The current/last-known presence of a single in-game player on a single server, derived
 * server-side from join/leave lines detected in Pterodactyl\Events\ConsoleArchive\ConsoleLinesCaptured
 * — see app/Services/Players/PlayerPresenceParser.php and PlayerPresenceService.php.
 *
 * This is a safeguarding/supervision tool ("which children are online, where") over derived
 * presence metadata, not message content — read access is gated by Permission::ACTION_PLAYERS_READ,
 * deliberately a lighter-weight permission than Permission::ACTION_ARCHIVE_READ (see that constant's
 * docblock in app/Models/Permission.php for the distinction).
 *
 * @property int $id
 * @property int $server_id
 * @property string $name
 * @property string $status
 * @property \Carbon\Carbon|null $joined_at
 * @property \Carbon\Carbon|null $last_seen_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Pterodactyl\Models\Server $server
 *
 * @method static Builder|ServerPlayer forServer(\Pterodactyl\Models\Server $server)
 * @method static Builder|ServerPlayer online()
 * @method static Builder|ServerPlayer query()
 */
class ServerPlayer extends Model
{
    use Prunable;

    public const RESOURCE_NAME = 'server_player';

    public const STATUS_ONLINE = 'online';
    public const STATUS_OFFLINE = 'offline';

    protected $table = 'server_players';

    protected $guarded = ['id'];

    protected $casts = [
        'server_id' => 'integer',
        'joined_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public static array $validationRules = [
        'server_id' => 'required|integer|exists:servers,id',
        'name' => 'required|string|max:64',
        'status' => 'required|string|in:online,offline',
        'joined_at' => 'nullable|date',
        'last_seen_at' => 'nullable|date',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Pterodactyl\Models\Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function scopeForServer(Builder $builder, Server $server): Builder
    {
        return $builder->where('server_id', $server->id);
    }

    public function scopeOnline(Builder $builder): Builder
    {
        return $builder->where('status', self::STATUS_ONLINE);
    }

    /**
     * Drops players who have been offline longer than config('players.prune_days').
     *
     * Only offline rows are eligible, and the cutoff is last_seen_at rather than created_at: a
     * regular who has played every day for a year is not stale, and pruning by row age would
     * remove them mid-session. A row with no last_seen_at at all has never been observed either
     * way and is left alone rather than guessed at.
     *
     * Deliberately Prunable, not MassPrunable: a player's recorded history in
     * server_player_sessions must survive them leaving the roster, so nothing cascades from here.
     */
    public function prunable(): Builder
    {
        $days = (int) config('players.prune_days', 7);

        if ($days <= 0) {
            // Never matches — a zero or negative setting disables pruning rather than deleting
            // the entire roster on the next scheduled run.
            return static::query()->whereRaw('1 = 0');
        }

        return static::query()
            ->where('status', self::STATUS_OFFLINE)
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '<=', now()->subDays($days));
    }
}
