<?php

namespace Pterodactyl\Transformers\Api\Client;

use Pterodactyl\Models\ServerPlayerSession;

class ServerPlayerSessionTransformer extends BaseClientTransformer
{
    public function getResourceName(): string
    {
        return ServerPlayerSession::RESOURCE_NAME;
    }

    public function transform(ServerPlayerSession $session): array
    {
        return [
            'id' => $session->id,
            'name' => $session->name,
            'event' => $session->event,
            'occurred_at' => $session->occurred_at->toAtomString(),
        ];
    }
}
