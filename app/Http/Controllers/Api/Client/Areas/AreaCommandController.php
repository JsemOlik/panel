<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Areas;

use Pterodactyl\Models\Area;
use Pterodactyl\Services\Consoles\AreaCommandService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Areas\SendAreaCommandRequest;

class AreaCommandController extends ClientApiController
{
    public function __construct(private AreaCommandService $service)
    {
        parent::__construct();
    }

    /**
     * Sends a single console command to every server in an area. See AreaCommandService for the
     * per-server permission/state/daemon handling.
     *
     * The result is wrapped in the same {object, attributes} envelope every other bulk/area action
     * in this API uses (see BulkPowerController and AreaPowerController) — the frontend helper
     * (sendAreaCommand.ts) unwraps `data.attributes`, so returning the result flat here would hand
     * it `undefined` at runtime without failing any backend test.
     *
     * @return array{object: string, attributes: array{command: string, succeeded: array, skipped: array, failed: array}}
     */
    public function index(SendAreaCommandRequest $request, Area $area): array
    {
        // The acting user is passed through so the service can skip individual servers they lack
        // control.console on — AreaPolicy::command() only gates whether the action may be
        // attempted at all. See AreaCommandService::send().
        $result = $this->service->send($area, $request->input('command'), $request->user());

        return [
            'object' => 'area_command_result',
            'attributes' => $result,
        ];
    }
}
