<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;
use Pterodactyl\Services\Players\PlayerModerationService;

/**
 * Requires Permission::ACTION_PLAYERS_MODERATE, not ACTION_PLAYERS_READ: staff who supervise by
 * watching the roster should not automatically be able to ban a child from it.
 *
 * The line-break rule below is a security control, not formatting. A console connection is line
 * oriented, so text containing a newline is two commands, and permitting it would let this narrow
 * permission issue arbitrary console input. It is enforced again in PlayerModerationService so
 * that the guarantee does not depend on every future caller going through this request class.
 */
class SendPlayerActionRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_PLAYERS_MODERATE;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:' . implode(',', [
                PlayerModerationService::ACTION_MESSAGE,
                PlayerModerationService::ACTION_KICK,
                PlayerModerationService::ACTION_BAN,
            ])],
            'text' => ['nullable', 'string', 'max:256', 'not_regex:/[\r\n]/'],
        ];
    }

    public function messages(): array
    {
        return [
            'text.not_regex' => 'Messages and reasons may not contain line breaks.',
        ];
    }
}
