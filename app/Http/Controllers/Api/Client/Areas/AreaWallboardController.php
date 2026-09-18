<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Areas;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Pterodactyl\Models\Area;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Services\Wallboard\WallboardStatusService;

/**
 * Serves the aggregate status payload the "wallboard" view polls: every area the requesting user
 * can see, each with its member servers' live-or-last-known status, in ONE response.
 *
 * Data-source decision (see WallboardStatusService docblock for the caching mechanics): this
 * deliberately does NOT open a Wings websocket per server, nor does it ask the browser to poll
 * the existing per-server `GET /servers/{uuid}/resources` endpoint once per server. At 13+
 * servers across several areas, either of those turns "one wallboard tab" into 13+ concurrent
 * connections or requests every refresh — for a display nobody is interacting with, that is pure
 * waste and a maintenance burden (13 reconnect state machines). A single aggregate endpoint
 * means one HTTP round trip per poll, one place to normalize "server offline" vs "node
 * unreachable" vs "no data yet at all" into a stable enum the frontend renders consistently, and
 * a shared Wings-side cache (see WallboardStatusService) that costs the daemon cluster no more
 * than the existing per-server resources endpoint already does.
 *
 * Visibility is filtered the exact same way AreaController::index() does — every area for a root
 * admin, otherwise only ones the user is assigned to as staff or has a relationship to via at
 * least one member server (delegated to AreaPolicy::view()) — so a wallboard token never surfaces
 * a server the viewer could not otherwise reach through the normal areas API.
 */
class AreaWallboardController extends ClientApiController
{
    public function __construct(private WallboardStatusService $service)
    {
        parent::__construct();
    }

    /**
     * @return array{object: string, attributes: array{generated_at: string, areas: array}}
     */
    public function index(Request $request): array
    {
        $areas = Area::query()->with(['servers.node'])->get();

        if (!$request->user()->root_admin) {
            $areas = $areas->filter(fn (Area $area) => $request->user()->can('view', $area))->values();
        }

        return [
            'object' => 'wallboard',
            'attributes' => [
                'generated_at' => Carbon::now()->toAtomString(),
                'areas' => $areas->map(fn (Area $area) => $this->service->transformArea($area))->values()->all(),
            ],
        ];
    }
}
