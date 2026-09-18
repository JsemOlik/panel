<?php

namespace Pterodactyl\Transformers\Api\Client;

use Pterodactyl\Models\ServerPlayer;

class ServerPlayerTransformer extends BaseClientTransformer
{
    public function getResourceName(): string
    {
        return ServerPlayer::RESOURCE_NAME;
    }

    public function transform(ServerPlayer $player): array
    {
        return [
            'id' => $player->id,
            'name' => $player->name,
            'status' => $player->status,
            'joined_at' => $player->joined_at?->toAtomString(),
            'last_seen_at' => $player->last_seen_at?->toAtomString(),
        ];
    }
}
