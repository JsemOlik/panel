<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Pterodactyl\Models\Area;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Subuser;
use Pterodactyl\Models\Permission;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Http\Requests\Admin\Area\AreaUserFormRequest;

class AreaUserController extends Controller
{
    /**
     * The default set of permissions granted to a staff member when they are auto-assigned as a
     * subuser on an area's member servers.
     */
    protected const DEFAULT_PERMISSIONS = [
        Permission::ACTION_CONTROL_CONSOLE,
        Permission::ACTION_CONTROL_START,
        Permission::ACTION_CONTROL_STOP,
        Permission::ACTION_CONTROL_RESTART,
    ];

    public function __construct(protected AlertsMessageBag $alert)
    {
    }

    /**
     * Assign a staff member to an area. Optionally also grants them subuser access (with a
     * sensible default permission set) to every member server currently in the area, so
     * assigning someone to an area is a one-click operation for the common case.
     */
    public function store(AreaUserFormRequest $request, Area $area): RedirectResponse
    {
        /** @var User $user */
        $user = User::query()->findOrFail($request->input('user_id'));

        $area->staff()->syncWithoutDetaching([$user->id]);

        if ($request->boolean('grant_subuser_access')) {
            foreach ($area->members as $server) {
                if ($server->owner_id === $user->id) {
                    continue;
                }

                Subuser::query()->updateOrCreate(
                    ['user_id' => $user->id, 'server_id' => $server->id],
                    ['permissions' => self::DEFAULT_PERMISSIONS]
                );
            }
        }

        $this->alert->success('The staff member was assigned to this area.')->flash();

        return redirect()->route('admin.areas.view', $area->id);
    }

    /**
     * Remove a staff member from an area. This does not revoke any subuser access they may have
     * been granted — that must be managed per-server as normal.
     */
    public function destroy(Area $area, int $user): RedirectResponse
    {
        $area->staff()->detach($user);

        $this->alert->success('The staff member was removed from this area.')->flash();

        return redirect()->route('admin.areas.view', $area->id);
    }
}
