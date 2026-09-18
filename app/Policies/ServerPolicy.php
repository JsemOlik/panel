<?php

namespace Pterodactyl\Policies;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;

class ServerPolicy
{
    /**
     * Checks if the user has the given permission on/for the server.
     */
    protected function checkPermission(User $user, Server $server, string $permission): bool
    {
        $subuser = $server->subusers->where('user_id', $user->id)->first();
        if (!$subuser || empty($permission)) {
            return false;
        }

        // A time-boxed grant that has passed its expiry is treated exactly as if
        // the subuser row did not exist at all: fail closed. This is the primary
        // enforcement point — every client API request routes through here via
        // Gate::can() -> ServerPolicy::before() -> checkPermission(), so this one
        // check covers files, databases, backups, schedules, startup, settings,
        // subuser management, and the websocket.connect permission used to mint
        // the websocket JWT.
        if ($subuser->isExpired()) {
            return false;
        }

        return in_array($permission, $subuser->permissions);
    }

    /**
     * Runs before any of the functions are called. Used to determine if user is root admin, if so, ignore permissions.
     */
    public function before(User $user, string $ability, Server $server): bool
    {
        if ($user->root_admin || $server->owner_id === $user->id) {
            return true;
        }

        return $this->checkPermission($user, $server, $ability);
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
