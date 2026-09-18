<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Ramsey\Uuid\Uuid;
use Illuminate\View\View;
use Pterodactyl\Models\Area;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Http\Requests\Admin\Area\AreaFormRequest;

class AreaController extends Controller
{
    public function __construct(protected AlertsMessageBag $alert)
    {
    }

    /**
     * Show all the areas currently configured on the system.
     */
    public function index(): View
    {
        $areas = Area::query()
            ->withCount(['servers', 'staff'])
            ->orderBy('name')
            ->paginate(25);

        return view('admin.areas.index', ['areas' => $areas]);
    }

    /**
     * Show the new area creation page.
     */
    public function create(): View
    {
        return view('admin.areas.new');
    }

    /**
     * Show the area management page, including its assigned servers and staff.
     */
    public function view(Area $area): View
    {
        $area->load(['servers.node', 'staff']);

        return view('admin.areas.view', [
            'area' => $area,
            'servers' => Server::query()->orderBy('name')->get(),
            'users' => User::query()->orderBy('username')->get(),
        ]);
    }

    /**
     * Store a newly created area.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     */
    public function store(AreaFormRequest $request): RedirectResponse
    {
        $area = Area::query()->create($request->areaData() + [
            'uuid' => Uuid::uuid4()->toString(),
        ]);

        $this->alert->success('The area was created successfully.')->flash();

        return redirect()->route('admin.areas.view', $area->id);
    }

    /**
     * Update an existing area's details.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     */
    public function update(AreaFormRequest $request, Area $area): RedirectResponse
    {
        $area->update($request->areaData());

        $this->alert->success('The area was updated successfully.')->flash();

        return redirect()->route('admin.areas.view', $area->id);
    }

    /**
     * Delete an area from the system. This does not affect any of its member servers or staff
     * users, it only removes the grouping.
     */
    public function destroy(Area $area): RedirectResponse
    {
        $area->delete();

        $this->alert->success('The area was deleted successfully.')->flash();

        return redirect()->route('admin.areas');
    }
}
