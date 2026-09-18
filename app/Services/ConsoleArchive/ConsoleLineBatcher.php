<?php

namespace Pterodactyl\Services\ConsoleArchive;

/**
 * Buffers CapturedConsoleLine instances for a single server and flushes them together, avoiding a
 * write-per-line hot loop against MySQL. A batch is flushed when either:
 *  - it reaches `batchSize` lines, or
 *  - `batchIntervalMs` milliseconds have elapsed since the oldest currently-buffered line
 *    (checked by calling `tick()`, which the caller is expected to do periodically — the ingestion
 *    daemon does this on every ReactPHP event-loop timer tick).
 *
 * Deliberately has no knowledge of Wings, websockets, or the database: `push()` just buffers, and
 * flushing invokes the `onFlush` callback with the buffered lines, in order. This is what makes it
 * unit-testable against a fake clock and a fake sink instead of a live Wings connection.
 *
 * One instance is created per server connection (never shared across servers), so a flush can
 * never mix lines from two servers into one batch — the caller relies on this to build one
 * ConsoleLinesCaptured event per flush.
 */
final class ConsoleLineBatcher
{
    /** @var CapturedConsoleLine[] */
    private array $buffer = [];

    private ?float $oldestBufferedAtSeconds = null;

    /**
     * @param \Closure(CapturedConsoleLine[]): void $onFlush
     * @param \Closure(): float|null $now Returns the current monotonic time in seconds. Defaults
     *                                     to `microtime(true)`; tests inject a fake clock instead.
     */
    public function __construct(
        private readonly int $batchSize,
        private readonly int $batchIntervalMs,
        private readonly \Closure $onFlush,
        private readonly ?\Closure $now = null,
    ) {
    }

    public function push(CapturedConsoleLine $line): void
    {
        if ($this->buffer === []) {
            $this->oldestBufferedAtSeconds = $this->currentTime();
        }

        $this->buffer[] = $line;

        if (count($this->buffer) >= $this->batchSize) {
            $this->flush();
        }
    }

    /**
     * Flushes the current buffer if it is non-empty and has been waiting longer than
     * `batchIntervalMs`. Safe to call as often as needed (e.g. every event-loop tick) — it is a
     * no-op when there is nothing due to flush.
     */
    public function tick(): void
    {
        if ($this->buffer === [] || $this->oldestBufferedAtSeconds === null) {
            return;
        }

        $elapsedMs = ($this->currentTime() - $this->oldestBufferedAtSeconds) * 1000;
        if ($elapsedMs >= $this->batchIntervalMs) {
            $this->flush();
        }
    }

    /**
     * Flushes whatever is currently buffered, regardless of size or age. Called on `tick()`/`push()`
     * as appropriate, and should also be called explicitly on graceful daemon shutdown and right
     * before a connection is torn down for reconnect, so a partial batch is never silently dropped.
     */
    public function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }

        $lines = $this->buffer;
        $this->buffer = [];
        $this->oldestBufferedAtSeconds = null;

        ($this->onFlush)($lines);
    }

    public function pendingCount(): int
    {
        return count($this->buffer);
    }

    private function currentTime(): float
    {
        return $this->now !== null ? ($this->now)() : microtime(true);
    }
}
