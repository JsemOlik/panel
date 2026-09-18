<?php

namespace Pterodactyl\Tests\Unit\Services\ConsoleArchive;

use Pterodactyl\Tests\TestCase;
use Pterodactyl\Models\ServerConsoleArchive;
use Pterodactyl\Services\ConsoleArchive\ConsoleLineClassifier;

class ConsoleLineClassifierTest extends TestCase
{
    public function testClassifiesVanillaChatWithTimestampAndLoggerPrefix(): void
    {
        $result = ConsoleLineClassifier::classify('[12:34:56] [Server thread/INFO]: <Steve> can we build a house?');

        $this->assertSame(ServerConsoleArchive::SOURCE_CHAT, $result['source']);
        $this->assertSame('Steve', $result['player']);
    }

    public function testClassifiesChatWithShortTimestampOnlyPrefix(): void
    {
        $result = ConsoleLineClassifier::classify('[12:34:56 INFO]: <Alex> hello!');

        $this->assertSame(ServerConsoleArchive::SOURCE_CHAT, $result['source']);
        $this->assertSame('Alex', $result['player']);
    }

    public function testClassifiesChatWithNoPrefixAtAll(): void
    {
        $result = ConsoleLineClassifier::classify('<Notch> anyone home?');

        $this->assertSame(ServerConsoleArchive::SOURCE_CHAT, $result['source']);
        $this->assertSame('Notch', $result['player']);
    }

    public function testChatMessageCanBeEmpty(): void
    {
        $result = ConsoleLineClassifier::classify('[12:34:56] [Server thread/INFO]: <Steve>');

        $this->assertSame(ServerConsoleArchive::SOURCE_CHAT, $result['source']);
        $this->assertSame('Steve', $result['player']);
    }

    public function testClassifiesJoinMessageAsConsoleWithPlayer(): void
    {
        $result = ConsoleLineClassifier::classify('[12:34:56] [Server thread/INFO]: Steve joined the game');

        $this->assertSame(ServerConsoleArchive::SOURCE_CONSOLE, $result['source']);
        $this->assertSame('Steve', $result['player']);
    }

    public function testClassifiesLeaveMessageAsConsoleWithPlayer(): void
    {
        $result = ConsoleLineClassifier::classify('[12:34:56] [Server thread/INFO]: Steve left the game');

        $this->assertSame(ServerConsoleArchive::SOURCE_CONSOLE, $result['source']);
        $this->assertSame('Steve', $result['player']);
    }

    public function testFallsBackToConsoleWithNoPlayerForUnrecognisedLines(): void
    {
        $result = ConsoleLineClassifier::classify('[12:34:56] [Server thread/INFO]: Preparing spawn area: 87%');

        $this->assertSame(ServerConsoleArchive::SOURCE_CONSOLE, $result['source']);
        $this->assertNull($result['player']);
    }

    public function testDoesNotMisclassifyModdedOrPluginPrefixedChatAsChat(): void
    {
        // Documented as a known limitation: a Discord-bridge/rank-prefixed chat format like
        // "[Staff] Steve: hello" does not match the vanilla "<name> message" shape, so it falls
        // back to console/no-player. The raw line is still stored either way.
        $result = ConsoleLineClassifier::classify('[12:34:56] [Server thread/INFO]: [Staff] Steve: hello');

        $this->assertSame(ServerConsoleArchive::SOURCE_CONSOLE, $result['source']);
        $this->assertNull($result['player']);
    }
}
