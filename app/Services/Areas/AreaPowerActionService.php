<?php

namespace Pterodactyl\Services\Areas;

use Illuminate\Support\Arr;
use Pterodactyl\Models\Area;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Facades\Activity;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Permission;
use Illuminate\Support\Facades\Gate;
use Pterodactyl\Repositories\Wings\DaemonPowerRepository;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Exceptions\Http\Server\ServerStateConflictException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Sequences power actions across an area (a proxy plus its member servers) so that the proxy
 * always comes up last and goes down first. This is the entire point of the "areas" feature —
 * without this ordering guarantee, players would be able to connect into a BungeeCord proxy with
 * no backends behind it, or get hard-disconnected instead of gracefully bounced when the area is
 * taken down for maintenance.
 *
 * This class is deliberately a plain synchronous service — `send()` has no knowledge of HTTP,
 * queues, or jobs. It is called synchronously from `AreaPowerController` today; if the sequencing
 * ever needs to move to a queued job (see the plan's risk notes on request timeouts), that job
 * should simply call `send()` unchanged rather than this class growing async-awareness itself.
 */
class AreaPowerActionService
{
    /**
     * The user this action is being performed on behalf of, and the control.* permission their
     * signal requires. Both are set for the duration of a single send() call and cleared
     * afterwards; when the actor is null no permission filtering happens at all, which is what
     * internal/system callers (a queued job, a console command) want.
     */
    private ?User $actor = null;

    private ?string $requiredPermission = null;

    public function __construct(
        private DaemonPowerRepository $powerRepository,
        private DaemonServerRepository $serverRepository,
    ) {
    }

    /**
     * Sends a sequenced power action to every server in an area the acting user is allowed to
     * control. AreaPolicy::power() has already established that they may act on the area at all
     * (it requires the matching permission on at least one server); this pass filters out the
     * individual servers they cannot touch, recording each as skipped. Passing a null $actor
     * disables that filtering entirely for internal callers.
     *
     * @return array{signal: string, succeeded: string[], skipped: string[], failed: string[]}
     */
    public function send(Area $area, string $signal, ?User $actor = null): array
    {
        $this->actor = $actor;
        $this->requiredPermission = match ($signal) {
            'start' => Permission::ACTION_CONTROL_START,
            'stop' => Permission::ACTION_CONTROL_STOP,
            // A restart is authorised as a restart throughout, even though it internally runs the
            // stop and start sequences — a user holding control.restart is not required to also
            // hold control.start and control.stop.
            'restart' => Permission::ACTION_CONTROL_RESTART,
            default => null,
        };

        try {
            return match ($signal) {
                'start' => $this->start($area),
                'stop' => $this->stop($area),
                'restart' => $this->restart($area),
                default => throw new \InvalidArgumentException("Unsupported area power signal [$signal]. Only start, stop and restart are allowed — kill is intentionally not exposed at the area level."),
            };
        } finally {
            $this->actor = null;
            $this->requiredPermission = null;
        }
    }

    /**
     * Starts every member server sequentially (in `sort_order`), then starts the proxy only once
     * at least one member has reported "running". A member that never reaches "running" within
     * the configured timeout is recorded as failed but does not block the rest of the members, or
     * the proxy, from starting. If *no* member reached "running", the proxy is skipped entirely —
     * a proxy with nothing behind it is worse than no proxy, since it would let a player connect
     * into a lobby with no destination server.
     *
     * @return array{signal: string, succeeded: string[], skipped: string[], failed: string[]}
     */
    private function start(Area $area): array
    {
        $result = $this->emptyResult('start');

        $anyMemberRunning = false;

        foreach ($this->members($area) as $server) {
            if (!$this->canAttempt($server, $result)) {
                continue;
            }

            $reachedRunning = $this->sendAndWait(
                $server,
                'start',
                fn (string $state) => $state === 'running',
                (int) config('pterodactyl.areas.start_timeout'),
                (int) config('pterodactyl.areas.start_poll_interval'),
            );

            if ($reachedRunning) {
                $anyMemberRunning = true;
                $result['succeeded'][] = $server->name;
                $this->logServerEvent($area, $server, 'start');
            } else {
                $result['failed'][] = $server->name;
            }
        }

        $proxy = $this->proxy($area);

        if ($proxy === null) {
            return $result;
        }

        if (!$anyMemberRunning) {
            $result['skipped'][] = $proxy->name;
            $this->logServerEvent($area, $proxy, 'start.skipped', ['reason' => 'no_members_running']);

            return $result;
        }

        if (!$this->canAttempt($proxy, $result)) {
            return $result;
        }

        try {
            $this->powerRepository->setServer($proxy)->send('start');
        } catch (DaemonConnectionException $exception) {
            Log::warning($exception, ['server' => $proxy->uuid, 'signal' => 'start', 'context' => 'area-power']);
            $result['failed'][] = $proxy->name;

            return $result;
        }

        $result['succeeded'][] = $proxy->name;
        $this->logServerEvent($area, $proxy, 'start');

        return $result;
    }

    /**
     * Stops the proxy first (so players are dropped from a screen that says "server restarting"
     * rather than being hard-disconnected once a backend they're on disappears), waits a shorter
     * bounded interval for it to leave "running", and only then stops the member servers. Members
     * are stopped independently of one another (order doesn't matter once players are off the
     * proxy) and independently of whether the proxy actually confirmed it stopped — a daemon that
     * can't be reached for the proxy must never block the members from being told to stop too.
     *
     * @return array{signal: string, succeeded: string[], skipped: string[], failed: string[]}
     */
    private function stop(Area $area): array
    {
        $result = $this->emptyResult('stop');

        $proxy = $this->proxy($area);

        if ($proxy !== null && $this->canAttempt($proxy, $result)) {
            $stopped = $this->sendAndWait(
                $proxy,
                'stop',
                fn (string $state) => $state !== 'running',
                (int) config('pterodactyl.areas.stop_timeout'),
                (int) config('pterodactyl.areas.stop_poll_interval'),
            );

            if ($stopped) {
                $result['succeeded'][] = $proxy->name;
                $this->logServerEvent($area, $proxy, 'stop');
            } else {
                $result['failed'][] = $proxy->name;
            }
        }

        foreach ($this->members($area) as $server) {
            if (!$this->canAttempt($server, $result)) {
                continue;
            }

            try {
                $this->powerRepository->setServer($server)->send('stop');
            } catch (DaemonConnectionException $exception) {
                Log::warning($exception, ['server' => $server->uuid, 'signal' => 'stop', 'context' => 'area-power']);
                $result['failed'][] = $server->name;

                continue;
            }

            $result['succeeded'][] = $server->name;
            $this->logServerEvent($area, $server, 'stop');
        }

        return $result;
    }

    /**
     * A restart is implemented as a full stop sequence followed by a full start sequence, never
     * as a raw per-server "restart" signal — the ordering guarantee (proxy down first, proxy up
     * last) is the entire point of this feature, and Wings' own per-server restart gives no
     * ordering at all across multiple servers. The final reported result reflects the start
     * phase, since that's what determines whether the area is actually usable again afterwards;
     * the stop phase still gets its own activity log entries (see stop()/logServerEvent()) so a
     * server that failed to stop cleanly is not silently lost from the audit trail.
     *
     * @return array{signal: string, succeeded: string[], skipped: string[], failed: string[]}
     */
    private function restart(Area $area): array
    {
        $this->stop($area);

        $result = $this->start($area);
        $result['signal'] = 'restart';

        return $result;
    }

    /**
     * Sends a signal to a server and then polls its state (via Wings) until the given predicate
     * matches or the timeout elapses. Returns false if the signal itself could not be delivered,
     * if the daemon becomes unreachable while polling, or if the timeout elapses without the
     * predicate ever matching.
     */
    private function sendAndWait(Server $server, string $signal, \Closure $reachedTarget, int $timeout, int $interval): bool
    {
        try {
            $this->powerRepository->setServer($server)->send($signal);
        } catch (DaemonConnectionException $exception) {
            Log::warning($exception, ['server' => $server->uuid, 'signal' => $signal, 'context' => 'area-power']);

            return false;
        }

        return $this->waitUntil($server, $reachedTarget, $timeout, $interval);
    }

    /**
     * Polls a server's current state from Wings on a bounded interval until the predicate matches
     * or the timeout is reached.
     */
    private function waitUntil(Server $server, \Closure $predicate, int $timeout, int $interval): bool
    {
        $interval = max(1, $interval);
        $elapsed = 0;

        while (true) {
            try {
                $details = $this->serverRepository->setServer($server)->getDetails();
            } catch (DaemonConnectionException $exception) {
                Log::warning($exception, ['server' => $server->uuid, 'context' => 'area-power-poll']);

                return false;
            }

            if ($predicate(Arr::get($details, 'state', 'offline'))) {
                return true;
            }

            if ($elapsed >= $timeout) {
                return false;
            }

            sleep($interval);
            $elapsed += $interval;
        }
    }

    /**
     * Checks whether a server is in a state where it can receive a power action at all (not
     * suspended, not under a node in maintenance, installed, not being transferred/restored). If
     * not, the server is recorded as skipped rather than failed — this mirrors
     * `BulkPowerController`'s handling of servers that can't currently receive a signal.
     */
    private function canAttempt(Server $server, array &$result): bool
    {
        // Servers the acting user can't control are skipped rather than failed: this is the
        // per-server half of the authorisation, paired with AreaPolicy::power()'s "at least one
        // server" gate. A partial-access staff member still gets a working button for the servers
        // they do control, and the ones they don't are reported back to them as skipped.
        if ($this->actor !== null && $this->requiredPermission !== null
            && !Gate::forUser($this->actor)->allows($this->requiredPermission, $server)) {
            $result['skipped'][] = $server->name;

            return false;
        }

        try {
            $server->validateCurrentState();
        } catch (ServerStateConflictException) {
            $result['skipped'][] = $server->name;

            return false;
        }

        return true;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Server>
     */
    private function members(Area $area): \Illuminate\Support\Collection
    {
        return $area->members()->with(['node', 'transfer'])->get();
    }

    private function proxy(Area $area): ?Server
    {
        return $area->proxy()->with(['node', 'transfer'])->first();
    }

    /**
     * @return array{signal: string, succeeded: string[], skipped: string[], failed: string[]}
     */
    private function emptyResult(string $signal): array
    {
        return ['signal' => $signal, 'succeeded' => [], 'skipped' => [], 'failed' => []];
    }

    /**
     * Logs a single server's part in a sequenced area power action, in addition to whatever
     * per-server activity `PowerController` would normally log — this keeps area actions visible
     * in the existing server/account activity log UI without any new UI work.
     */
    private function logServerEvent(Area $area, Server $server, string $suffix, array $properties = []): void
    {
        Activity::event("area:power.$suffix")
            ->subject($area, $server)
            ->property(array_merge(['area' => $area->name], $properties))
            ->log();
    }
}
