<?php

namespace Pterodactyl\Http\Requests\Api\Client\Areas;

use Pterodactyl\Models\Area;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class SendAreaCommandRequest extends ClientApiRequest
{
    /**
     * Determine if the user has permission to send a console command to every server in this
     * area. The route parameter is `area`, not `server`, so — exactly like SendAreaPowerRequest —
     * this can't rely on ClientApiRequest's generic authorize() shortcut and instead goes through
     * AreaPolicy::command() directly, which requires control.console on at least one of the
     * area's servers. Servers the user cannot individually send console commands to are then
     * skipped by AreaCommandService rather than failing the whole request.
     */
    public function authorize(): bool
    {
        $area = $this->route()->parameter('area');

        if (!$area instanceof Area) {
            return false;
        }

        return $this->user()->can('command', $area);
    }

    public function rules(): array
    {
        return [
            'command' => 'required|string|min:1',
        ];
    }
}
