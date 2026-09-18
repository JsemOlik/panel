<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Builder;
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
}
