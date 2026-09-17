<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Pterodactyl\Models\Server;
use Pterodactyl\Facades\Activity;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Repositories\Wings\DaemonPowerRepository;
use Pterodactyl\Http\Requests\Api\Client\SendBulkPowerRequest;
use Pterodactyl\Exceptions\Http\Server\ServerStateConflictException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

class BulkPowerController extends ClientApiController
{
    public function __construct(private DaemonPowerRepository $repository)
    {
        parent::__construct();
    }

    /**
     * Sends a power action to every server owned by the user. Servers that can't receive
     * it right now (suspended, installing, transferring...) are skipped, and servers whose
     * daemon can't be reached are reported as failed without stopping the others.
     */
    public function __invoke(SendBulkPowerRequest $request): array
    {
        $signal = $request->input('signal');
        $result = ['signal' => $signal, 'succeeded' => [], 'skipped' => [], 'failed' => []];

        $servers = Server::query()
            ->with(['node', 'transfer'])
            ->where('owner_id', $request->user()->id)
            ->orderBy('name')
            ->get();

        foreach ($servers as $server) {
            try {
                $server->validateCurrentState();
            } catch (ServerStateConflictException) {
                $result['skipped'][] = $server->name;

                continue;
            }

            try {
                $this->repository->setServer($server)->send($signal);
            } catch (DaemonConnectionException $exception) {
                Log::warning($exception, ['server' => $server->uuid, 'signal' => $signal]);
                $result['failed'][] = $server->name;

                continue;
            }

            Activity::event("server:power.$signal")->subject($server)->property('bulk', true)->log();

            $result['succeeded'][] = $server->name;
        }

        return [
            'object' => 'bulk_power_result',
            'attributes' => $result,
        ];
    }
}
