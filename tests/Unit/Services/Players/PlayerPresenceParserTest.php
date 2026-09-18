<?php

namespace Pterodactyl\Tests\Unit\Services\Players;

use Pterodactyl\Tests\TestCase;
use Pterodactyl\Services\Players\PlayerPresenceParser;

class PlayerPresenceParserTest extends TestCase
{
    // ---- Backend (vanilla/Paper/Spigot) ----

    public function testDetectsBackendJoinFromChatBroadcastWithFullPrefix(): void
    {
        $result = PlayerPresenceParser::parse('[12:34:56] [Server thread/INFO]: Steve joined the game', false);

        $this->assertSame(['event' => 'join', 'player' => 'Steve'], $result);
    }

    public function testDetectsBackendJoinFromChatBroadcastWithShortPrefix(): void
    {
        $result = PlayerPresenceParser::parse('[12:34:56 INFO]: Alex joined the game', false);

        $this->assertSame(['event' => 'join', 'player' => 'Alex'], $result);
    }

    public function testDetectsBackendJoinFromEntityLoginLineEvenWhenBroadcastIsSuppressed(): void
    {
        $result = PlayerPresenceParser::parse(
            '[12:34:56] [Server thread/INFO]: Notch[/127.0.0.1:54321] logged in with entity id 123 at (1.0, 2.0, 3.0)',
            false
        );

        $this->assertSame(['event' => 'join', 'player' => 'Notch'], $result);
    }

    public function testDetectsBackendLeave(): void
    {
        $result = PlayerPresenceParser::parse('[12:34:56] [Server thread/INFO]: Steve left the game', false);

        $this->assertSame(['event' => 'leave', 'player' => 'Steve'], $result);
    }

    public function testBackendChatIsNotMisdetectedAsJoinOrLeave(): void
    {
        $result = PlayerPresenceParser::parse('[12:34:56] [Server thread/INFO]: <Steve> did you join the game yet?', false);

        $this->assertNull($result);
    }

    public function testUnrecognisedBackendLineReturnsNull(): void
    {
        $result = PlayerPresenceParser::parse('[12:34:56] [Server thread/INFO]: Preparing spawn area: 87%', false);

        $this->assertNull($result);
    }

    public function testProxyStyleLineIsNotDetectedWhenParsedAsBackend(): void
    {
        // A proxy line handed in with isProxy=false (misconfigured egg detection, say) should
        // simply fail to match rather than being mis-parsed as something else.
        $result = PlayerPresenceParser::parse('[Steve] has connected', false);

        $this->assertNull($result);
    }

    // ---- Proxy (BungeeCord/Waterfall) ----

    public function testDetectsProxyConnect(): void
    {
        $result = PlayerPresenceParser::parse('[12:34:56 INFO]: [Steve] has connected', true);

        $this->assertSame(['event' => 'join', 'player' => 'Steve'], $result);
    }

    public function testDetectsProxyDisconnect(): void
    {
        $result = PlayerPresenceParser::parse('[12:34:56 INFO]: [Steve] has disconnected', true);

        $this->assertSame(['event' => 'leave', 'player' => 'Steve'], $result);
    }

    public function testProxyBackendSwitchIsNotTreatedAsNetworkJoin(): void
    {
        $result = PlayerPresenceParser::parse('[12:34:56 INFO]: [Steve] -> lobby has connected', true);

        $this->assertNull($result);
    }

    public function testProxyBackendSwitchIsNotTreatedAsNetworkLeave(): void
    {
        $result = PlayerPresenceParser::parse('[12:34:56 INFO]: [Steve] -> lobby has disconnected', true);

        $this->assertNull($result);
    }

    public function testBackendStyleLineIsNotDetectedWhenParsedAsProxy(): void
    {
        $result = PlayerPresenceParser::parse('[12:34:56] [Server thread/INFO]: Steve joined the game', true);

        $this->assertNull($result);
    }

    public function testEmptyLineReturnsNull(): void
    {
        $this->assertNull(PlayerPresenceParser::parse('', false));
        $this->assertNull(PlayerPresenceParser::parse('[12:34:56 INFO]: ', false));
    }
}
