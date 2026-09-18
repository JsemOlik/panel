<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

/**
 * Authorization is enforced by ClientApiRequest::authorize() calling $this->user()->can($this->permission(),
 * $server), which routes through ServerPolicy — a root admin or the server owner always passes, a
 * subuser needs Permission::ACTION_PLAYERS_READ explicitly granted.
 */
class GetServerPlayersRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_PLAYERS_READ;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'in:online,offline'],
        ];
    }
}
