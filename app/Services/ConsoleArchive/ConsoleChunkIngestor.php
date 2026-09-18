<?php

namespace Pterodactyl\Services\ConsoleArchive;

/**
 * Turns one raw `console output` websocket payload (a single string, possibly containing
 * multiple newline-separated lines) into zero or more CapturedConsoleLine instances: split on
 * line breaks, ANSI/control-character stripped, length-capped, and classified.
 *
 * This is the entire "what do we do with a chunk of Wings console output" pipeline, deliberately
 * kept free of any websocket/Wings/database concerns so it can be unit tested directly against
 * fixture strings instead of a live Wings connection.
 */
final class ConsoleChunkIngestor
{
    private const TRUNCATION_SUFFIX = ' …[truncated]';

    /**
     * @param \Closure(): \DateTimeImmutable|null $now Defaults to `new \DateTimeImmutable()`;
     *                                                   tests inject a fixed clock instead.
     */
    public function __construct(
        private readonly int $maxLineLength = 4096,
        private readonly ?\Closure $now = null,
    ) {
    }

    /**
     * @return CapturedConsoleLine[]
     */
    public function ingest(int $serverId, string $chunk): array
    {
        $lines = [];

        foreach (preg_split('/\r\n|\r|\n/', $chunk) ?: [] as $raw) {
            $stripped = trim(AnsiStripper::strip($raw));
            if ($stripped === '') {
                continue;
            }

            $stripped = $this->truncate($stripped);
            $classification = ConsoleLineClassifier::classify($stripped);

            $lines[] = new CapturedConsoleLine(
                serverId: $serverId,
                loggedAt: $this->currentTime(),
                line: $stripped,
                source: $classification['source'],
                player: $classification['player'],
            );
        }

        return $lines;
    }

    private function truncate(string $line): string
    {
        if ($this->maxLineLength <= 0 || mb_strlen($line) <= $this->maxLineLength) {
            return $line;
        }

        return mb_substr($line, 0, $this->maxLineLength) . self::TRUNCATION_SUFFIX;
    }

    private function currentTime(): \DateTimeImmutable
    {
        return $this->now !== null ? ($this->now)() : new \DateTimeImmutable();
    }
}
