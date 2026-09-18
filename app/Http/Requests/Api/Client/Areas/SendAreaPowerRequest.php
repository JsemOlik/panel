<?php

namespace Pterodactyl\Http\Requests\Api\Client\Areas;

use Pterodactyl\Models\Area;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class SendAreaPowerRequest extends ClientApiRequest
{
    /**
     * Determine if the user has permission to send this power signal to every member server of
     * the area. The route parameter is `area`, not `server`, so this can't rely on
     * ClientApiRequest's generic `authorize()` shortcut — it goes straight through
     * `AreaPolicy::power()` instead, which requires the corresponding control.* permission on at
     * least one of the area's servers. Servers the user cannot control are then skipped
     * individually by AreaPowerActionService rather than failing the whole request.
     */
    public function authorize(): bool
    {
        $area = $this->route()->parameter('area');

        if (!$area instanceof Area) {
            return false;
        }

        return $this->user()->can('power', [$area, $this->input('signal')]);
    }

    public function rules(): array
    {
        return [
            'signal' => 'required|string|in:start,stop,restart',
        ];
    }
}
