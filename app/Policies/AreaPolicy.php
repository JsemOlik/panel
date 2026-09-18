<?php

namespace Pterodactyl\Policies;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Area;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Gate;
use Pterodactyl\Models\Permission;

class AreaPolicy
{
    /**
     * Runs before any of the functions are called. Used to determine if the user is a root
     * admin, if so, ignore any of the other checks below.
     */
    public function before(User $user, string $ability, Area $area): ?bool
    {
        return $user->root_admin ? true : null;
    }

    /**
     * A user may view an area if they've been assigned to it as staff, or if they have any
     * relationship (ownership or subuser) to at least one of its member servers. The per-server
     * check is delegated to ServerPolicy (via the Gate) so this policy never has to re-derive
     * subuser/ownership logic of its own.
     */
    public function view(User $user, Area $area): bool
    {
        if ($area->staff->contains('id', $user->id)) {
            return true;
        }

        return $area->servers->contains(
            fn (Server $server) => Gate::forUser($user)->allows(Permission::ACTION_WEBSOCKET_CONNECT, $server)
        );
    }

    /**
     * A user may send a power signal to an area if they hold the matching control.* permission on
     * at least one of its servers. This gate only decides whether the action may be attempted at
     * all — it deliberately does not require the permission on *every* server, because staff here
     * rotate between areas and routinely hold access to only part of one. Refusing the whole
     * action in that case would leave them with no working button at all.
     *
     * Enforcement of the remaining servers happens per-server inside AreaPowerActionService,
     * which skips any server the acting user lacks this same permission on. The two must agree:
     * loosening this check without that per-server filter would let a user with access to one
     * server power-cycle an entire area.
     *
     * As above, the actual per-server permission check is delegated to ServerPolicy via the Gate.
     */
    public function power(User $user, Area $area, string $signal): bool
    {
        $permission = match ($signal) {
            'start' => Permission::ACTION_CONTROL_START,
            'stop' => Permission::ACTION_CONTROL_STOP,
            'restart' => Permission::ACTION_CONTROL_RESTART,
            default => null,
        };

        if (is_null($permission) || $area->servers->isEmpty()) {
            return false;
        }

        return $area->servers->contains(
            fn (Server $server) => Gate::forUser($user)->allows($permission, $server)
        );
    }

    /**
     * A user may send a console command to an area if they hold control.console on at least one
     * of its servers — same "at least one, not all" shape as power(), and for the same reason:
     * staff rotate between areas and routinely hold access to only part of one. As with power(),
     * this gate only decides whether the action may be attempted at all; AreaCommandService skips
     * the individual servers the actor lacks control.console on. The two must agree — loosening
     * this check without that per-server filter would let a user with console access to one server
     * broadcast a command to an entire area.
     */
    public function command(User $user, Area $area): bool
    {
        if ($area->servers->isEmpty()) {
            return false;
        }

        return $area->servers->contains(
            fn (Server $server) => Gate::forUser($user)->allows(Permission::ACTION_CONTROL_CONSOLE, $server)
        );
    }

    /**
     * This is a horrendous hack to avoid Laravel's "smart" behavior that does
     * not call the before() function if there isn't a function matching the
     * policy permission.
     */
    public function __call(string $name, mixed $arguments)
    {
        // do nothing
    }
}
