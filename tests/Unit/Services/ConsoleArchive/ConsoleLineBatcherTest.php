<?php

namespace Pterodactyl\Tests\Unit\Services\ConsoleArchive;

use Pterodactyl\Tests\TestCase;
use Pterodactyl\Services\ConsoleArchive\CapturedConsoleLine;
use Pterodactyl\Services\ConsoleArchive\ConsoleLineBatcher;

class ConsoleLineBatcherTest extends TestCase
{
    private function makeLine(string $text = 'a line'): CapturedConsoleLine
    {
        return new CapturedConsoleLine(1, new \DateTimeImmutable(), $text);
    }

    public function testFlushesAutomaticallyOnceBatchSizeIsReached(): void
    {
        $flushed = [];
        $batcher = new ConsoleLineBatcher(3, 999999, function (array $lines) use (&$flushed) {
            $flushed[] = $lines;
        });

        $batcher->push($this->makeLine('one'));
        $batcher->push($this->makeLine('two'));
        $this->assertCount(0, $flushed, 'must not flush before batch size is reached');
        $this->assertSame(2, $batcher->pendingCount());

        $batcher->push($this->makeLine('three'));

        $this->assertCount(1, $flushed);
        $this->assertCount(3, $flushed[0]);
        $this->assertSame(['one', 'two', 'three'], array_map(fn (CapturedConsoleLine $l) => $l->line, $flushed[0]));
        $this->assertSame(0, $batcher->pendingCount());
    }

    public function testTickFlushesOnceIntervalHasElapsedEvenBelowBatchSize(): void
    {
        $flushed = [];
        $clock = 1000.0;

        $batcher = new ConsoleLineBatcher(
            100,
            1500,
            function (array $lines) use (&$flushed) {
                $flushed[] = $lines;
            },
            function () use (&$clock) {
                return $clock;
            },
        );

        $batcher->push($this->makeLine());
        $batcher->tick();
        $this->assertCount(0, $flushed, 'must not flush before the interval elapses');

        $clock += 1.0; // 1000ms elapsed, still under the 1500ms interval
        $batcher->tick();
        $this->assertCount(0, $flushed);

        $clock += 0.6; // now 1600ms elapsed
        $batcher->tick();
        $this->assertCount(1, $flushed);
    }

    public function testTickIsANoOpWhenBufferIsEmpty(): void
    {
        $flushed = [];
        $batcher = new ConsoleLineBatcher(10, 100, function (array $lines) use (&$flushed) {
            $flushed[] = $lines;
        });

        $batcher->tick();
        $batcher->tick();

        $this->assertCount(0, $flushed);
    }

    public function testExplicitFlushSendsWhateverIsBufferedAndResets(): void
    {
        $flushed = [];
        $batcher = new ConsoleLineBatcher(100, 999999, function (array $lines) use (&$flushed) {
            $flushed[] = $lines;
        });

        $batcher->push($this->makeLine('only one'));
        $batcher->flush();

        $this->assertCount(1, $flushed);
        $this->assertCount(1, $flushed[0]);
        $this->assertSame(0, $batcher->pendingCount());

        // Flushing again with nothing buffered must not invoke the callback a second time.
        $batcher->flush();
        $this->assertCount(1, $flushed);
    }

    public function testNeverMixesLinesAcrossFlushes(): void
    {
        $flushed = [];
        $batcher = new ConsoleLineBatcher(2, 999999, function (array $lines) use (&$flushed) {
            $flushed[] = $lines;
        });

        $batcher->push($this->makeLine('a'));
        $batcher->push($this->makeLine('b')); // flush #1
        $batcher->push($this->makeLine('c'));
        $batcher->push($this->makeLine('d')); // flush #2

        $this->assertCount(2, $flushed);
        $this->assertSame(['a', 'b'], array_map(fn (CapturedConsoleLine $l) => $l->line, $flushed[0]));
        $this->assertSame(['c', 'd'], array_map(fn (CapturedConsoleLine $l) => $l->line, $flushed[1]));
    }
}
