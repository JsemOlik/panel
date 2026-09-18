<?php

namespace Pterodactyl\Services\ConsoleArchive;

/**
 * Exponential backoff with a hard ceiling, used by the ingestion daemon to decide how long to
 * wait before re-opening a per-server Wings websocket after it closes unexpectedly (Wings
 * restart, network blip, node reboot). Deliberately deterministic (no jitter) so it is trivial to
 * unit test and reason about; ~13 servers reconnecting in lockstep after a shared node restart is
 * not enough concurrency for the thundering-herd problem jitter exists to solve.
 */
final class ReconnectBackoff
{
    private int $attempts = 0;

    public function __construct(
        private readonly int $baseSeconds = 1,
        private readonly int $maxSeconds = 60,
    ) {
    }

    /**
     * Returns the delay (seconds) to wait before the next reconnect attempt, and records that an
     * attempt is being made. Doubles every call: base, 2*base, 4*base, ... capped at maxSeconds.
     */
    public function nextDelaySeconds(): int
    {
        $delay = (int) min($this->maxSeconds, $this->baseSeconds * (2 ** $this->attempts));
        ++$this->attempts;

        return $delay;
    }

    /**
     * Call this once a connection is successfully (re-)established so the next failure starts
     * backing off from the base delay again, rather than continuing to escalate.
     */
    public function reset(): void
    {
        $this->attempts = 0;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }
}
