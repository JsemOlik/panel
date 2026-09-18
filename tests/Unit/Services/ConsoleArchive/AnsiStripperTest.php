<?php

namespace Pterodactyl\Tests\Unit\Services\ConsoleArchive;

use Pterodactyl\Tests\TestCase;
use Pterodactyl\Services\ConsoleArchive\AnsiStripper;

class AnsiStripperTest extends TestCase
{
    public function testStripsColorCodes(): void
    {
        $raw = "\x1b[32m[12:34:56 INFO]:\x1b[0m \x1b[1;37m<Steve>\x1b[0m hello there";

        $this->assertSame('[12:34:56 INFO]: <Steve> hello there', AnsiStripper::strip($raw));
    }

    public function testStripsCursorMovementAndOtherCsiSequences(): void
    {
        $raw = "\x1b[2K\x1b[1G[12:34:56] loading...";

        $this->assertSame('[12:34:56] loading...', AnsiStripper::strip($raw));
    }

    public function testStripsOscSequenceTerminatedByBell(): void
    {
        $raw = "\x1b]0;window title\x07plain text";

        $this->assertSame('plain text', AnsiStripper::strip($raw));
    }

    public function testStripsBareControlCharactersButKeepsTabs(): void
    {
        $raw = "line\x00with\x07control\x1fchars\tand a tab";

        $this->assertSame('linewithcontrolchars	and a tab', AnsiStripper::strip($raw));
    }

    public function testLeavesPlainTextUntouched(): void
    {
        $raw = '<Alex> can we build a house near the river?';

        $this->assertSame($raw, AnsiStripper::strip($raw));
    }

    public function testHandlesEmptyString(): void
    {
        $this->assertSame('', AnsiStripper::strip(''));
    }
}
