<?php

namespace Pterodactyl\Tests\Unit\Services\ConsoleArchive;

use Pterodactyl\Tests\TestCase;
use Pterodactyl\Models\ServerConsoleArchive;
use Pterodactyl\Services\ConsoleArchive\ConsoleChunkIngestor;

class ConsoleChunkIngestorTest extends TestCase
{
    public function testSplitsAMultiLineChunkIntoSeparateCapturedLines(): void
    {
        $ingestor = new ConsoleChunkIngestor(4096);

        $lines = $ingestor->ingest(7, "[12:34:56] [Server thread/INFO]: <Steve> hi\n[12:34:57] [Server thread/INFO]: <Alex> hey!");

        $this->assertCount(2, $lines);
        $this->assertSame(7, $lines[0]->serverId);
        $this->assertSame('[12:34:56] [Server thread/INFO]: <Steve> hi', $lines[0]->line);
        $this->assertSame(ServerConsoleArchive::SOURCE_CHAT, $lines[0]->source);
        $this->assertSame('Steve', $lines[0]->player);

        $this->assertSame('Alex', $lines[1]->player);
    }

    public function testStripsAnsiCodesBeforeStoringOrClassifying(): void
    {
        $ingestor = new ConsoleChunkIngestor(4096);

        $lines = $ingestor->ingest(1, "\x1b[36m[12:34:56] [Server thread/INFO]: \x1b[0m<Steve> \x1b[32mhi\x1b[0m");

        $this->assertCount(1, $lines);
        $this->assertSame('[12:34:56] [Server thread/INFO]: <Steve> hi', $lines[0]->line);
        $this->assertSame('Steve', $lines[0]->player);
    }

    public function testDropsBlankLinesFromTheChunk(): void
    {
        $ingestor = new ConsoleChunkIngestor(4096);

        $lines = $ingestor->ingest(1, "first line\n\n   \nsecond line");

        $this->assertCount(2, $lines);
        $this->assertSame('first line', $lines[0]->line);
        $this->assertSame('second line', $lines[1]->line);
    }

    public function testTruncatesLinesLongerThanTheConfiguredMaximum(): void
    {
        $ingestor = new ConsoleChunkIngestor(20);

        $lines = $ingestor->ingest(1, str_repeat('x', 50));

        $this->assertCount(1, $lines);
        $this->assertSame(str_repeat('x', 20) . ' …[truncated]', $lines[0]->line);
    }

    public function testDoesNotTruncateLinesAtOrUnderTheMaximum(): void
    {
        $ingestor = new ConsoleChunkIngestor(10);

        $lines = $ingestor->ingest(1, str_repeat('x', 10));

        $this->assertSame(str_repeat('x', 10), $lines[0]->line);
    }

    public function testUsesTheInjectedClockForLoggedAt(): void
    {
        $fixed = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $ingestor = new ConsoleChunkIngestor(4096, fn () => $fixed);

        $lines = $ingestor->ingest(1, 'a line');

        $this->assertSame($fixed, $lines[0]->loggedAt);
    }

    public function testHandlesCarriageReturnLineFeedAndBareCarriageReturnLineEndings(): void
    {
        $ingestor = new ConsoleChunkIngestor(4096);

        $lines = $ingestor->ingest(1, "one\r\ntwo\rthree");

        $this->assertSame(['one', 'two', 'three'], array_map(fn ($l) => $l->line, $lines));
    }
}
