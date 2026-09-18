<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers;

use Pterodactyl\Models\Permission;
use Pterodactyl\Models\ServerConsoleArchive;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

/**
 * Authorization is enforced by ClientApiRequest::authorize() calling $this->user()->can($this->permission(),
 * $server), which routes through ServerPolicy — a root admin or the server owner always passes,
 * a subuser needs Permission::ACTION_ARCHIVE_READ explicitly granted. See that permission's
 * description in app/Models/Permission.php for why it is its own permission rather than reusing
 * activity.read or control.console.
 */
class ConsoleArchiveSearchRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_ARCHIVE_READ;
    }

    public function rules(): array
    {
        return [
            'query' => ['nullable', 'string', 'max:255'],
            'player' => ['nullable', 'string', 'max:64'],
            'source' => ['nullable', 'string', 'in:' . ServerConsoleArchive::SOURCE_CONSOLE . ',' . ServerConsoleArchive::SOURCE_CHAT],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
