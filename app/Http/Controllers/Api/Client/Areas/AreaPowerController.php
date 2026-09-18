<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Areas;

use Pterodactyl\Models\Area;
use Pterodactyl\Services\Areas\AreaPowerActionService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Areas\SendAreaPowerRequest;

class AreaPowerController extends ClientApiController
{
    public function __construct(private AreaPowerActionService $service)
    {
        parent::__construct();
    }

    /**
     * Sends a sequenced start/stop/restart signal to every server in an area. See
     * AreaPowerActionService for the ordering and partial-failure semantics.
     *
     * The result is wrapped in the same {object, attributes} envelope BulkPowerController uses,
     * because that is what every client-side power helper unwraps (see sendAreaPowerAction.ts and
     * sendBulkPowerAction.ts, both of which read `data.attributes`). Returning the result flat
     * here would hand the frontend `undefined` at runtime without failing any backend test.
     *
     * @return array{object: string, attributes: array{signal: string, succeeded: string[], skipped: string[], failed: string[]}}
     */
    public function index(SendAreaPowerRequest $request, Area $area): array
    {
        // The acting user is passed through so the service can skip individual servers they lack
        // the control.* permission on — AreaPolicy::power() only gates whether the action may be
        // attempted at all. See AreaPowerActionService::send().
        $result = $this->service->send($area, $request->input('signal'), $request->user());

        return [
            'object' => 'area_power_result',
            'attributes' => $result,
        ];
    }
}
