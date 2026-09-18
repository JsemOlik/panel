<?php

namespace Pterodactyl\Services\Consoles;

use Pterodactyl\Models\Area;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Facades\Activity;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Permission;
use GuzzleHttp\Exception\BadResponseException;
use Pterodactyl\Repositories\Wings\DaemonCommandRepository;
use Pterodactyl\Exceptions\Http\Server\ServerStateConflictException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Fans a single console command out to every server in an area (members and the proxy alike —
 * unlike {@see \Pterodactyl\Services\Areas\AreaPowerActionService}, a console command has no
 * "proxy must come up last" ordering concern, so this is a plain synchronous loop like
 * {@see \Pterodactyl\Http\Controllers\Api\Client\BulkPowerController}).
 *
 * Mirrors the single-server semantics of {@see \Pterodactyl\Http\Controllers\Api\Client\Servers\CommandController}
 * per server: same daemon call, same 502-means-"offline" interpretation, same
 * `server:console.command` activity event (with a `bulk` property so it's distinguishable in the
 * server's own activity feed from a command typed directly into its console).
 */
class AreaCommandService
{
    public function __construct(private DaemonCommandRepository $repository)
    {
    }

    /**
     * Sends $command to every server in $area the acting user is allowed to send console commands
     * to. AreaPolicy::command() has already established the actor may attempt the action at all
     * (it requires control.console on at least one of the area's servers); this pass filters out
     * the individual servers they cannot touch, recording each as skipped — the same split
     * responsibility AreaPolicy::power() documents for power actions. Passing a null $actor
     * disables that filtering entirely, for internal/system callers.
     *
     * @return array{command: string, succeeded: array, skipped: array, failed: array}
     */
    public function send(Area $area, string $command, ?User $actor = null): array
    {
        $result = ['command' => $command, 'succeeded' => [], 'skipped' => [], 'failed' => []];

        $servers = $area->servers()->with(['node', 'transfer'])->get()->sortBy('name')->values();

        foreach ($servers as $server) {
            if ($actor !== null && !Gate::forUser($actor)->allows(Permission::ACTION_CONTROL_CONSOLE, $server)) {
                $result['skipped'][] = $this->describe($server, 'no_permission');

                continue;
            }

            try {
                $server->validateCurrentState();
            } catch (ServerStateConflictException) {
                $result['skipped'][] = $this->describe($server, $this->conflictReason($server));

                continue;
            }

            try {
                $this->repository->setServer($server)->send($command);
            } catch (DaemonConnectionException $exception) {
                Log::warning($exception, ['server' => $server->uuid, 'context' => 'area-command']);

                $previous = $exception->getPrevious();
                $reason = $previous instanceof BadResponseException
                    && $previous->getResponse()->getStatusCode() === Response::HTTP_BAD_GATEWAY
                    ? 'offline'
                    : 'unreachable';

                $result['failed'][] = $this->describe($server, $reason);

                continue;
            }

            Activity::event('server:console.command')
                ->subject($area, $server)
                ->property(['command' => $command, 'bulk' => true, 'area' => $area->name])
                ->log();

            $result['succeeded'][] = $this->describe($server);
        }

        return $result;
    }

    private function conflictReason(Server $server): string
    {
        return match (true) {
            $server->isSuspended() => 'suspended',
            !is_null($server->transfer) => 'transferring',
            $server->status === Server::STATUS_RESTORING_BACKUP => 'restoring_backup',
            !$server->isInstalled() => 'installing',
            $server->node->isUnderMaintenance() => 'node_under_maintenance',
            default => 'unavailable',
        };
    }

    /**
     * @return array{id: int, uuid: string, name: string, reason?: string}
     */
    private function describe(Server $server, ?string $reason = null): array
    {
        $data = ['id' => $server->id, 'uuid' => $server->uuid, 'name' => $server->name];

        if ($reason !== null) {
            $data['reason'] = $reason;
        }

        return $data;
    }
}
