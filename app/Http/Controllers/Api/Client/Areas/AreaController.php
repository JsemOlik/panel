<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Areas;

use Illuminate\Http\Request;
use Pterodactyl\Models\Area;
use Pterodactyl\Transformers\Api\Client\AreaTransformer;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;

class AreaController extends ClientApiController
{
    /**
     * Lists every area the current user can reach: all of them for a root admin, otherwise only
     * those they're assigned to as staff or have a relationship (ownership or subuser) to at
     * least one member server of — delegated to AreaPolicy::view() per area.
     */
    public function index(Request $request): array
    {
        $areas = Area::query()->with(['servers.node', 'staff'])->get();

        if (!$request->user()->root_admin) {
            $areas = $areas->filter(fn (Area $area) => $request->user()->can('view', $area))->values();
        }

        return $this->fractal->collection($areas)
            ->transformWith($this->getTransformer(AreaTransformer::class))
            ->toArray();
    }

    /**
     * Returns a single area with all of its member servers, each transformed using the existing
     * ServerTransformer so the frontend gets the same Server shape it already knows how to render.
     */
    public function view(Request $request, Area $area): array
    {
        $this->authorize('view', $area);

        $area->loadMissing(['servers.node', 'staff']);

        return $this->fractal->item($area)
            ->transformWith($this->getTransformer(AreaTransformer::class))
            ->toArray();
    }
}
