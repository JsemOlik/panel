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
     * @return array{signal: string, succeeded: string[], skipped: string[], failed: string[]}
     */
    public function index(SendAreaPowerRequest $request, Area $area): array
    {
        // The acting user is passed through so the service can skip individual servers they lack
        // the control.* permission on — AreaPolicy::power() only gates whether the action may be
        // attempted at all. See AreaPowerActionService::send().
        return $this->service->send($area, $request->input('signal'), $request->user());
    }
}
