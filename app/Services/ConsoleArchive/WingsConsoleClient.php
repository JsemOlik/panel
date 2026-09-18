<?php

namespace Pterodactyl\Services\ConsoleArchive;

use Ratchet\Client\WebSocket;
use Ratchet\Client\Connector;
use Pterodactyl\Models\Server;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Ratchet\RFC6455\Messaging\MessageInterface;

/**
 * Owns exactly one Wings websocket connection, for exactly one server, connecting the same way a
 * browser does (see resources/scripts/plugins/Websocket.ts and WebsocketHandler.tsx) except that
 * it authenticates as the daemon itself via WingsConsoleTokenBroker rather than a real user
 * session, and it never stops — there is no browser tab to close.
 *
 * Responsibilities:
 *  - open the connection and send the `auth` event with a freshly minted JWT;
 *  - on `console output`, run each payload through ConsoleChunkIngestor and push the resulting
 *    lines into a per-server ConsoleLineBatcher;
 *  - on `token expiring`/`token expired`, mint a new token and re-send `auth` on the same socket
 *    (mirrors WebsocketHandler.tsx's updateToken());
 *  - on `jwt error` or an unexpected `close`, flush whatever is buffered and reconnect with
 *    exponential backoff (ReconnectBackoff) rather than let the archive silently stop.
 *
 * This class is intentionally the *only* place in the ingestion daemon that touches a live
 * websocket. Everything it delegates to (ConsoleChunkIngestor, ConsoleLineClassifier,
 * AnsiStripper, ConsoleLineBatcher, ReconnectBackoff) is pure and unit tested without a
 * connection; this class itself is exercised only by the daemon in a real environment, since
 * faking a Wings server well enough to be a meaningful test is not worth the cost for ~13 servers
 * — see the command's docblock for how to verify it against a real Wings instance by hand.
 */
final class WingsConsoleClient
{
    private ?WebSocket $connection = null;

    private readonly ReconnectBackoff $backoff;

    private readonly ConsoleLineBatcher $batcher;

    private readonly ConsoleChunkIngestor $ingestor;

    private bool $stopped = false;

    /**
     * @param \Closure(CapturedConsoleLine[]): void $onFlush
     */
    public function __construct(
        private readonly LoopInterface $loop,
        private readonly Connector $connector,
        private readonly WingsConsoleTokenBroker $tokenBroker,
        private readonly Server $server,
        \Closure $onFlush,
        private readonly LoggerInterface $logger,
    ) {
        $this->backoff = new ReconnectBackoff(
            (int) config('console_archive.ingest.reconnect_base_seconds', 1),
            (int) config('console_archive.ingest.reconnect_max_seconds', 60),
        );

        $this->batcher = new ConsoleLineBatcher(
            (int) config('console_archive.ingest.batch_size', 200),
            (int) config('console_archive.ingest.batch_interval_ms', 1500),
            $onFlush,
        );

        $this->ingestor = new ConsoleChunkIngestor(
            (int) config('console_archive.ingest.max_line_length', 4096),
        );
    }

    public function start(): void
    {
        $this->connect();
    }

    /**
     * Called on every daemon event-loop tick so the batcher's time-based flush actually fires
     * even when the server is quiet (no lines arriving to trigger a size-based flush).
     */
    public function tick(): void
    {
        $this->batcher->tick();
    }

    /**
     * Flushes any buffered lines and closes the connection. Called on graceful daemon shutdown
     * and when a server drops out of the reconcile list (deleted/suspended).
     */
    public function stop(): void
    {
        $this->stopped = true;
        $this->batcher->flush();
        $this->connection?->close();
    }

    private function connect(): void
    {
        if ($this->stopped) {
            return;
        }

        $token = $this->tokenBroker->mintToken($this->server);
        $url = $this->tokenBroker->socketUrl($this->server);

        // Wings refuses the upgrade with a bare 403 unless the Origin header matches its
        // configured panel location (see CheckOrigin in wings/router/websocket/websocket.go).
        // A browser sets this automatically, which is why the handshake works there and not
        // here; without it the daemon connects, is rejected, and retries forever while logging
        // only "403 Forbidden" with no indication that a header is the reason.
        ($this->connector)($url, [], ['Origin' => rtrim(config('app.url'), '/')])->then(
            function (WebSocket $conn) use ($token) {
                if ($this->stopped) {
                    $conn->close();

                    return;
                }

                $this->connection = $conn;
                $this->backoff->reset();
                $conn->send(json_encode(['event' => 'auth', 'args' => [$token]]));

                $conn->on('message', function (MessageInterface $msg) {
                    $this->handleMessage((string) $msg);
                });

                $conn->on('close', function () {
                    $this->connection = null;
                    $this->batcher->flush();
                    $this->scheduleReconnect();
                });

                $conn->on('error', function (\Throwable $e) {
                    $this->logger->warning('console-archive: websocket error', [
                        'server' => $this->server->uuid,
                        'error' => $e->getMessage(),
                    ]);
                });
            },
            function (\Throwable $e) {
                $this->logger->warning('console-archive: failed to connect to Wings', [
                    'server' => $this->server->uuid,
                    'error' => $e->getMessage(),
                ]);
                $this->scheduleReconnect();
            }
        );
    }

    private function scheduleReconnect(): void
    {
        if ($this->stopped) {
            return;
        }

        $delay = $this->backoff->nextDelaySeconds();
        $this->logger->info('console-archive: reconnecting to Wings', [
            'server' => $this->server->uuid,
            'delay_seconds' => $delay,
            'attempt' => $this->backoff->attempts(),
        ]);
        $this->loop->addTimer($delay, fn () => $this->connect());
    }

    private function handleMessage(string $raw): void
    {
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return;
        }

        if (!is_array($decoded)) {
            return;
        }

        $event = $decoded['event'] ?? null;
        $args = $decoded['args'] ?? [];

        switch ($event) {
            case 'console output':
                foreach ((array) $args as $chunk) {
                    foreach ($this->ingestor->ingest($this->server->id, (string) $chunk) as $line) {
                        $this->batcher->push($line);
                    }
                }
                break;

            case 'token expiring':
            case 'token expired':
                $this->refreshToken();
                break;

            case 'jwt error':
                // Mirrors WebsocketHandler.tsx's reconnectErrors handling: rather than try to
                // distinguish recoverable from fatal JWT errors, just tear down and reconnect
                // from scratch with a freshly minted token — safe for an unattended daemon since
                // the alternative (getting silently stuck) is worse than a reconnect.
                $this->logger->info('console-archive: jwt error from Wings, reconnecting', [
                    'server' => $this->server->uuid,
                    'error' => $args[0] ?? null,
                ]);
                $this->connection?->close();
                break;

            default:
                break;
        }
    }

    private function refreshToken(): void
    {
        if ($this->connection === null) {
            return;
        }

        $token = $this->tokenBroker->mintToken($this->server);
        $this->connection->send(json_encode(['event' => 'auth', 'args' => [$token]]));
    }
}
