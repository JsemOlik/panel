<?php

namespace Pterodactyl\Http\Requests\Api\Client;

use Pterodactyl\Models\Task;

class SendBulkPowerRequest extends ClientApiRequest
{
    /**
     * Only servers owned by the user are affected, so there is no extra permission to check.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'signal' => 'required|string|in:' . implode(',', Task::POWER_ACTIONS),
        ];
    }
}
