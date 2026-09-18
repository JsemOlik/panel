<?php

namespace Pterodactyl\Transformers\Api\Client;

use Pterodactyl\Models\Area;
use Pterodactyl\Models\Server;

class AreaTransformer extends BaseClientTransformer
{
    public function getResourceName(): string
    {
        return Area::RESOURCE_NAME;
    }

    /**
     * Transforms an area, embedding every member server using the existing ServerTransformer so
     * the frontend gets the exact same server shape it already knows how to render (status, name,
     * limits, ...) elsewhere in the dashboard. Each embedded server also carries its area-specific
     * `area_role` ("proxy" | "member") and `area_sort_order` from the pivot row, so the frontend
     * can render the start/stop ordering without a second lookup.
     */
    public function transform(Area $area): array
    {
        /** @var ServerTransformer $serverTransformer */
        $serverTransformer = $this->makeTransformer(ServerTransformer::class);

        $members = $area->servers
            // Members first (in their configured start order), proxy always last — this mirrors
            // the actual start sequence the power action service applies.
            ->sortBy(fn (Server $server) => $this->pivotAttribute($server, 'role') === Area::ROLE_PROXY
                ? PHP_INT_MAX
                : $this->pivotAttribute($server, 'sort_order'))
            ->map(fn (Server $server) => array_merge($serverTransformer->transform($server), [
                'area_role' => $this->pivotAttribute($server, 'role'),
                'area_sort_order' => $this->pivotAttribute($server, 'sort_order'),
            ]))
            ->values()
            ->all();

        return [
            'id' => $area->id,
            'uuid' => $area->uuid,
            'name' => $area->name,
            'description' => $area->description,
            'created_at' => $area->created_at->toAtomString(),
            'updated_at' => $area->updated_at->toAtomString(),
            'members' => $members,
        ];
    }

    /**
     * Reads a value off a server's `area_server` pivot row (role, sort_order). Uses
     * `getAttribute()` rather than the `->pivot->...` magic property directly, since the pivot
     * relation on `Server` has no static type declared for static analysis to follow.
     */
    private function pivotAttribute(Server $server, string $key): mixed
    {
        return $server->getAttribute('pivot')?->getAttribute($key);
    }
}
