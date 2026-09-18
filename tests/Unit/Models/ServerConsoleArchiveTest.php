<?php

namespace Pterodactyl\Tests\Unit\Models;

use Pterodactyl\Tests\TestCase;
use Pterodactyl\Models\ServerConsoleArchive;
use Pterodactyl\Services\ConsoleArchive\CapturedConsoleLine;

class ServerConsoleArchiveTest extends TestCase
{
    public function testInsertRowsFromBuildsPlainArrayRowsInOrder(): void
    {
        $loggedAt = new \DateTimeImmutable('2026-01-01T12:00:00+00:00');

        $lines = [
            new CapturedConsoleLine(5, $loggedAt, '<Steve> hi', ServerConsoleArchive::SOURCE_CHAT, 'Steve'),
            new CapturedConsoleLine(5, $loggedAt, 'Preparing spawn area'),
        ];

        $rows = ServerConsoleArchive::insertRowsFrom($lines);

        $this->assertCount(2, $rows);
        $this->assertSame([
            'server_id' => 5,
            'logged_at' => '2026-01-01 12:00:00',
            'line' => '<Steve> hi',
            'source' => ServerConsoleArchive::SOURCE_CHAT,
            'player' => 'Steve',
        ], $rows[0]);

        $this->assertSame([
            'server_id' => 5,
            'logged_at' => '2026-01-01 12:00:00',
            'line' => 'Preparing spawn area',
            'source' => ServerConsoleArchive::SOURCE_CONSOLE,
            'player' => null,
        ], $rows[1]);
    }

    public function testInsertRowsFromReturnsEmptyArrayForEmptyInput(): void
    {
        $this->assertSame([], ServerConsoleArchive::insertRowsFrom([]));
    }
}
