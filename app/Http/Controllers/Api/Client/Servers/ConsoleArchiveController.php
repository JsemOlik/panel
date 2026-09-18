<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerConsoleArchive;
use Pterodactyl\Transformers\Api\Client\ConsoleArchiveTransformer;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\ConsoleArchiveSearchRequest;

/**
 * Searches a server's captured console/chat archive (see app/Models/ServerConsoleArchive.php and
 * the ingestion daemon at app/Console/Commands/ConsoleArchive/ConsumeConsoleArchiveCommand.php).
 *
 * Access control: gated by ConsoleArchiveSearchRequest::permission() (Permission::ACTION_ARCHIVE_READ),
 * enforced via ServerPolicy exactly like every other server-scoped client endpoint — a root admin
 * or the server owner always has access, a subuser must be explicitly granted `archive.read`. This
 * archive is bulk-captured children's chat, so unlike most server permissions it is deliberately
 * NOT implied by owning/managing the server in any other capacity beyond owner/root-admin.
 */
class ConsoleArchiveController extends ClientApiController
{
    /**
     * MySQL InnoDB fulltext search (`MATCH ... AGAINST ... IN BOOLEAN MODE` via Laravel's
     * whereFullText()) over `line`, filterable by player, best-effort source classification, and
     * a logged_at time range — see plan-log-archive.md §3 for why fulltext-over-MySQL is
     * sufficient at this fleet's volume (~13 servers) rather than a dedicated search engine.
     */
    public function __invoke(ConsoleArchiveSearchRequest $request, Server $server): array
    {
        $validated = $request->validated();

        $query = ServerConsoleArchive::query()->forServer($server);

        if (!empty($validated['query'])) {
            $query->whereFullText('line', $validated['query']);
        }

        if (!empty($validated['player'])) {
            $query->where('player', $validated['player']);
        }

        if (!empty($validated['source'])) {
            $query->where('source', $validated['source']);
        }

        if (!empty($validated['from'])) {
            $query->where('logged_at', '>=', $validated['from']);
        }

        if (!empty($validated['to'])) {
            $query->where('logged_at', '<=', $validated['to']);
        }

        $entries = $query
            ->orderByDesc('logged_at')
            ->orderByDesc('id')
            ->paginate(min((int) ($validated['per_page'] ?? 50), 100))
            ->appends($request->query());

        return $this->fractal->collection($entries)
            ->transformWith($this->getTransformer(ConsoleArchiveTransformer::class))
            ->toArray();
    }
}
