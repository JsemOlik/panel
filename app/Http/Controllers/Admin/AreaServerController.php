<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Pterodactyl\Models\Area;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Http\Requests\Admin\Area\AreaServerFormRequest;

class AreaServerController extends Controller
{
    public function __construct(protected AlertsMessageBag $alert)
    {
    }

    /**
     * Attach a server to an area with the given role.
     */
    public function store(AreaServerFormRequest $request, Area $area): RedirectResponse
    {
        $area->servers()->attach($request->input('server_id'), $request->serverData());

        $this->alert->success('The server was added to this area.')->flash();

        return redirect()->route('admin.areas.view', $area->id);
    }

    /**
     * Update the role/sort order of a server already assigned to an area.
     */
    public function update(AreaServerFormRequest $request, Area $area, int $pivot): RedirectResponse
    {
        $row = DB::table('area_server')->where('id', $pivot)->first();

        abort_unless($row && (int) $row->area_id === $area->id, 404);

        DB::table('area_server')->where('id', $pivot)->update($request->serverData() + [
            'updated_at' => now(),
        ]);

        $this->alert->success('The server assignment was updated.')->flash();

        return redirect()->route('admin.areas.view', $area->id);
    }

    /**
     * Remove a server from an area.
     */
    public function destroy(Area $area, int $pivot): RedirectResponse
    {
        $row = DB::table('area_server')->where('id', $pivot)->first();

        abort_unless($row && (int) $row->area_id === $area->id, 404);

        DB::table('area_server')->where('id', $pivot)->delete();

        $this->alert->success('The server was removed from this area.')->flash();

        return redirect()->route('admin.areas.view', $area->id);
    }
}
