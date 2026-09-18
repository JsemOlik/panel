<?php

namespace Pterodactyl\Transformers\Api\Client;

use Pterodactyl\Models\ServerConsoleArchive;

class ConsoleArchiveTransformer extends BaseClientTransformer
{
    public function getResourceName(): string
    {
        return ServerConsoleArchive::RESOURCE_NAME;
    }

    public function transform(ServerConsoleArchive $entry): array
    {
        return [
            'id' => $entry->id,
            'logged_at' => $entry->logged_at->toAtomString(),
            'line' => $entry->line,
            'source' => $entry->source,
            'player' => $entry->player,
        ];
    }
}
