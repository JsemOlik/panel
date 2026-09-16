<?php

namespace Pterodactyl\Http\Controllers\Admin\Nests;

use Illuminate\View\View;
use Pterodactyl\Models\Egg;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\EggConsoleShortcut;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Http\Requests\Admin\Egg\EggConsoleShortcutFormRequest;

class EggConsoleShortcutController extends Controller
{
    public function __construct(protected AlertsMessageBag $alert)
    {
    }

    /**
     * Show the console shortcuts defined for an egg.
     */
    public function index(Egg $egg): View
    {
        $egg->load('nest', 'consoleShortcuts');

        return view('admin.eggs.shortcuts', ['egg' => $egg]);
    }

    /**
     * Create a new console shortcut for the egg.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     */
    public function store(EggConsoleShortcutFormRequest $request, Egg $egg): RedirectResponse
    {
        $egg->consoleShortcuts()->create($request->shortcutData());
        $this->alert->success('The console shortcut was created.')->flash();

        return redirect()->route('admin.nests.egg.shortcuts', $egg->id);
    }

    /**
     * Update an existing console shortcut.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     */
    public function update(EggConsoleShortcutFormRequest $request, Egg $egg, EggConsoleShortcut $shortcut): RedirectResponse
    {
        abort_unless($shortcut->egg_id === $egg->id, 404);

        $shortcut->update($request->shortcutData());
        $this->alert->success(sprintf('The "%s" console shortcut was updated.', e($shortcut->name)))->flash();

        return redirect()->route('admin.nests.egg.shortcuts', $egg->id);
    }

    /**
     * Delete a console shortcut.
     */
    public function destroy(Egg $egg, EggConsoleShortcut $shortcut): RedirectResponse
    {
        abort_unless($shortcut->egg_id === $egg->id, 404);

        $shortcut->delete();
        $this->alert->success(sprintf('The "%s" console shortcut was deleted.', e($shortcut->name)))->flash();

        return redirect()->route('admin.nests.egg.shortcuts', $egg->id);
    }
}
