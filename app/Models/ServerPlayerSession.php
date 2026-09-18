<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * \Pterodactyl\Models\ServerPlayerSession.
 *
 * An append-only log of detected join/leave events for a player on a server — the per-player
 * "what has this player been doing" activity feed. See app/Services/Players/PlayerPresenceService.php,
 * which writes both this table and the current-state ServerPlayer row from the same detected event.
 *
 * @property int $id
 * @property int $server_id
 * @property string $name
 * @property string $event
 * @property \Carbon\Carbon $occurred_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Pterodactyl\Models\Server $server
 *
 * @method static Builder|ServerPlayerSession forServer(\Pterodactyl\Models\Server $server)
 * @method static Builder|ServerPlayerSession forPlayer(string $name)
 * @method static Builder|ServerPlayerSession query()
 */
class ServerPlayerSession extends Model
{
    use MassPrunable;

    public const RESOURCE_NAME = 'server_player_session';

    public const EVENT_JOIN = 'join';
    public const EVENT_LEAVE = 'leave';

    public $timestamps = false;

    protected $table = 'server_player_sessions';

    protected $guarded = ['id'];

    protected $casts = [
        'server_id' => 'integer',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public static array $validationRules = [
        'server_id' => 'required|integer|exists:servers,id',
        'name' => 'required|string|max:64',
        'event' => 'required|string|in:join,leave',
        'occurred_at' => 'required|date',
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

    public function scopeForPlayer(Builder $builder, string $name): Builder
    {
        return $builder->where('name', $name);
    }

    /**
     * Drops join/leave events older than config('players.session_prune_days').
     *
     * Deliberately MassPrunable, not Prunable: this is a high-volume append-only log with no
     * cascading relationships and nothing listening for its model events, so there is nothing to
     * gain from hydrating every row and firing deleting/deleted for each one — a mass DELETE is
     * the correct tool for a log table of this shape, the same way ActivityLog and the console
     * archive prune themselves.
     */
    public function prunable(): Builder
    {
        $days = (int) config('players.session_prune_days', 90);

        if ($days <= 0) {
            // Never matches — a zero or negative setting disables pruning rather than deleting
            // the entire history table on the next scheduled run.
            return static::query()->whereRaw('1 = 0');
        }

        return static::query()->where('occurred_at', '<=', now()->subDays($days));
    }
}
