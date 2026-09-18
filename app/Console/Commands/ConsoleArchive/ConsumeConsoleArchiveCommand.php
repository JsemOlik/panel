<?php

namespace Pterodactyl\Console\Commands\ConsoleArchive;

use Ratchet\Client\Connector;
use Illuminate\Console\Command;
use Pterodactyl\Models\Server;
use React\EventLoop\LoopInterface;
use Psr\Log\LoggerInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Pterodactyl\Models\ServerConsoleArchive;
use Pterodactyl\Events\ConsoleArchive\ConsoleLinesCaptured;
use Pterodactyl\Services\ConsoleArchive\WingsConsoleClient;
use Pterodactyl\Services\ConsoleArchive\WingsConsoleTokenBroker;

/**
 * The console/chat archive ingestion daemon.
 *
 * This is a LONG-LIVED process, not a scheduled/cron job — it must be run under a process
 * supervisor (see the class docblock section "Running this" below), never via Laravel's
 * scheduler. It opens one persistent Wings websocket connection per active server (connecting
 * exactly like a browser viewing the console does, see resources/scripts/plugins/Websocket.ts),
 * authenticated with a JWT the Panel mints for itself via WingsConsoleTokenBroker /
 * NodeJWTService — no browser or staff session needs to be open for capture to happen, which is
 * the entire point: the safeguarding use case is reconstructing what was said when nobody was
 * watching live.
 *
 * What it does, per server:
 *  1. Connects and authenticates (WingsConsoleClient).
 *  2. On `console output`, strips ANSI codes, classifies each line (best-effort chat/console +
 *     player), and buffers it (ConsoleLineBatcher) instead of writing row-by-row.
 *  3. Flushes a buffered batch to `server_console_archives` via one multi-row INSERT, then
 *     dispatches ONE Pterodactyl\Events\ConsoleArchive\ConsoleLinesCaptured event carrying that
 *     whole batch — this is the integration point downstream features (player-activity tracking,
 *     keyword alerts) should hook into. See that event class's docblock for the full contract.
 *  4. Reconnects with exponential backoff (ReconnectBackoff) if the socket closes unexpectedly
 *     (Wings restart, network blip, node reboot), and proactively re-authenticates on
 *     `token expiring`/`token expired` exactly like the browser client does.
 *
 * The server list is re-queried periodically (config('console_archive.ingest.server_reconcile_seconds'))
 * as well as at boot, so a newly created server is picked up without restarting the daemon, and a
 * deleted/suspended server's connection is torn down (flushing whatever was buffered first).
 *
 * ------------------------------------------------------------------------------------------------
 * Running this (dev + production)
 * ------------------------------------------------------------------------------------------------
 * This fork's dev environment (dev/build/panel/configs/supervisor/conf.d/) already runs
 * `artisan queue:listen` under Supervisor alongside php-fpm/nginx/cron — this command belongs
 * next to it as another `[program:...]` entry, e.g.:
 *
 *   [program:console-archive]
 *   process_name=%(program_name)s
 *   user=www-data
 *   autostart=true
 *   autorestart=true
 *   startretries=5
 *   command=/usr/bin/php /var/www/html/artisan p:console-archive:consume -v
 *   stdout_logfile=/dev/stdout
 *   stdout_logfile_maxbytes=0
 *   stderr_logfile=/dev/stderr
 *   stderr_logfile_maxbytes=0
 *
 * and the equivalent single `[program:console-archive]` block for the production Supervisor
 * config at .github/docker/supervisord.conf's sibling in a real deployment. It must NOT be added
 * to Kernel::schedule() — Laravel's scheduler starts a fresh process per tick and would either
 * stack overlapping instances or (with ->withoutOverlapping()) just restart the websocket
 * connections every minute, defeating the point of a persistent connection.
 *
 * ------------------------------------------------------------------------------------------------
 * NOT verified against a live Wings instance
 * ------------------------------------------------------------------------------------------------
 * Every pure piece of this pipeline (ANSI stripping, chat/console classification, batching,
 * backoff, chunk-to-lines ingestion) is unit tested against fakes. The websocket handshake/auth/
 * reconnect flow in WingsConsoleClient itself is NOT exercised by an automated test against a
 * real or fake Wings server — that would need either a live node or a non-trivial mock websocket
 * server, neither of which was set up here. It was checked by reading Wings' own source
 * (dev/code/wings/router/websocket/{websocket,listeners}.go and router/tokens/websocket.go) to
 * confirm the message shapes and auth/permission requirements match what this class sends and
 * expects.
 */
class ConsumeConsoleArchiveCommand extends Command
{
    protected $signature = 'p:console-archive:consume';

    protected $description = 'Long-lived daemon that connects to every active server\'s Wings websocket and archives console/chat output. Run under a process supervisor, not the scheduler.';

    /** @var array<int, WingsConsoleClient> keyed by server id */
    private array $clients = [];

    public function __construct(
        private readonly LoopInterface $loop,
        private readonly WingsConsoleTokenBroker $tokenBroker,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!config('console_archive.enabled', true)) {
            $this->components->warn('console_archive.enabled is false; not starting the ingestion daemon.');

            return self::SUCCESS;
        }

        $connector = new Connector($this->loop);

        $this->reconcileServers($connector);

        $tickIntervalSeconds = 0.25;
        $this->loop->addPeriodicTimer($tickIntervalSeconds, function () {
            foreach ($this->clients as $client) {
                $client->tick();
            }
        });

        $reconcileSeconds = max(5, (int) config('console_archive.ingest.server_reconcile_seconds', 120));
        $this->loop->addPeriodicTimer($reconcileSeconds, function () use ($connector) {
            $this->reconcileServers($connector);
        });

        if (function_exists('pcntl_signal') && extension_loaded('pcntl')) {
            $shutdown = function () {
                $this->components->info('console-archive: shutting down, flushing buffered batches...');
                foreach ($this->clients as $client) {
                    $client->stop();
                }
                $this->loop->stop();
            };
            $this->loop->addSignal(SIGTERM, $shutdown);
            $this->loop->addSignal(SIGINT, $shutdown);
        }

        $this->components->info(sprintf('console-archive: watching %d server(s)', count($this->clients)));
        $this->loop->run();

        return self::SUCCESS;
    }

    /**
     * Re-queries the set of servers that should be archived and reconciles it against the
     * currently-open connections: starts clients for newly-eligible servers, stops clients for
     * servers that are no longer eligible (deleted, suspended, or still installing).
     */
    private function reconcileServers(Connector $connector): void
    {
        $eligible = Server::query()
            ->with('node')
            ->whereNotIn('status', [
                Server::STATUS_INSTALLING,
                Server::STATUS_INSTALL_FAILED,
                Server::STATUS_SUSPENDED,
            ])
            ->get()
            ->keyBy('id');

        foreach ($this->clients as $serverId => $client) {
            if (!$eligible->has($serverId)) {
                $client->stop();
                unset($this->clients[$serverId]);
            }
        }

        foreach ($eligible as $serverId => $server) {
            if (isset($this->clients[$serverId])) {
                continue;
            }

            $client = new WingsConsoleClient(
                $this->loop,
                $connector,
                $this->tokenBroker,
                $server,
                $this->makeFlushHandler($server),
                $this->logger,
            );
            $client->start();

            $this->clients[$serverId] = $client;
        }
    }

    /**
     * Builds the per-server batch-flush callback: one multi-row INSERT, then one
     * ConsoleLinesCaptured event for the whole batch. See that event's docblock for the consumer
     * contract downstream features build against.
     *
     * @return \Closure(\Pterodactyl\Services\ConsoleArchive\CapturedConsoleLine[]): void
     */
    private function makeFlushHandler(Server $server): \Closure
    {
        return function (array $lines) use ($server) {
            if ($lines === []) {
                return;
            }

            try {
                DB::table('server_console_archives')->insert(ServerConsoleArchive::insertRowsFrom($lines));
            } catch (\Throwable $e) {
                // A batch is dropped rather than retried on a write failure: retrying risks an
                // unbounded backlog building up in memory against a database that is down, and
                // this daemon has no durable outbox. This is a real, accepted gap — the archive
                // is "as complete as MySQL availability allows", not guaranteed-lossless — and
                // should be paired with alerting on this log line in production.
                $this->logger->error('console-archive: failed to persist a batch, dropping it', [
                    'server' => $server->uuid,
                    'lines' => count($lines),
                    'error' => $e->getMessage(),
                ]);

                return;
            }

            Event::dispatch(new ConsoleLinesCaptured($server, $lines));
        };
    }
}
