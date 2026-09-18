<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Groups servers together as an "area" — a fixed set of game servers plus, optionally, a single
 * proxy server that should always be started last and stopped first. Staff assignment to an area
 * (via {@see Area::staff()}) is purely an organisational convenience; actual per-server
 * permissions continue to flow through {@see Subuser} and {@see Permission} as normal.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $description
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property \Illuminate\Database\Eloquent\Collection|\Pterodactyl\Models\Server[] $servers
 * @property \Illuminate\Database\Eloquent\Collection|\Pterodactyl\Models\Server[] $members
 * @property \Illuminate\Database\Eloquent\Collection|\Pterodactyl\Models\User[] $staff
 */
class Area extends Model
{
    /**
     * The resource name for this model when it is transformed into an
     * API representation using fractal.
     */
    public const RESOURCE_NAME = 'area';

    /**
     * The "role" a server plays within an area's pivot row.
     */
    public const ROLE_PROXY = 'proxy';
    public const ROLE_MEMBER = 'member';

    protected bool $immutableDates = true;

    protected $table = 'areas';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    public static array $validationRules = [
        'uuid' => 'required|string|size:36',
        'name' => 'required|string|between:1,191',
        'description' => 'nullable|string',
    ];

    /**
     * All servers belonging to this area, regardless of their role.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\Pterodactyl\Models\Server, $this>
     */
    public function servers(): BelongsToMany
    {
        // The pivot's own `id` is selected deliberately: the admin UI addresses individual
        // area_server rows by it when changing a server's role or detaching it from the area.
        return $this->belongsToMany(Server::class, 'area_server')
            ->withPivot(['id', 'role', 'sort_order'])
            ->withTimestamps();
    }

    /**
     * The single proxy server for this area, if one has been assigned. There should never be more
     * than one — that is enforced at the form/service layer rather than the database.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\Pterodactyl\Models\Server, $this>
     */
    public function proxy(): BelongsToMany
    {
        return $this->servers()->wherePivot('role', self::ROLE_PROXY);
    }

    /**
     * All non-proxy ("member") servers for this area, ordered by their configured start order.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\Pterodactyl\Models\Server, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->servers()->wherePivot('role', self::ROLE_MEMBER)->orderByPivot('sort_order');
    }

    /**
     * Staff assigned to this area. This is an organisational grouping only — it does not itself
     * grant any server permissions, those still come from {@see Subuser}.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\Pterodactyl\Models\User, $this>
     */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'area_user')->withTimestamps();
    }
}
