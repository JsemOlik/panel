<?php

namespace Pterodactyl\Tests\Unit\Services\ConsoleArchive;

use Pterodactyl\Tests\TestCase;
use Pterodactyl\Services\ConsoleArchive\ReconnectBackoff;

class ReconnectBackoffTest extends TestCase
{
    public function testDoublesEachAttemptUntilTheCeiling(): void
    {
        $backoff = new ReconnectBackoff(baseSeconds: 1, maxSeconds: 60);

        $this->assertSame(1, $backoff->nextDelaySeconds());
        $this->assertSame(2, $backoff->nextDelaySeconds());
        $this->assertSame(4, $backoff->nextDelaySeconds());
        $this->assertSame(8, $backoff->nextDelaySeconds());
        $this->assertSame(16, $backoff->nextDelaySeconds());
        $this->assertSame(32, $backoff->nextDelaySeconds());
        $this->assertSame(60, $backoff->nextDelaySeconds(), 'must be capped at maxSeconds');
        $this->assertSame(60, $backoff->nextDelaySeconds(), 'must stay capped on further attempts');
    }

    public function testResetStartsBackoffFromTheBaseAgain(): void
    {
        $backoff = new ReconnectBackoff(baseSeconds: 2, maxSeconds: 30);

        $backoff->nextDelaySeconds();
        $backoff->nextDelaySeconds();
        $this->assertSame(2, $backoff->attempts());

        $backoff->reset();

        $this->assertSame(0, $backoff->attempts());
        $this->assertSame(2, $backoff->nextDelaySeconds());
    }

    public function testAttemptsCounterTracksCallCount(): void
    {
        $backoff = new ReconnectBackoff();

        $this->assertSame(0, $backoff->attempts());
        $backoff->nextDelaySeconds();
        $this->assertSame(1, $backoff->attempts());
        $backoff->nextDelaySeconds();
        $this->assertSame(2, $backoff->attempts());
    }
}
