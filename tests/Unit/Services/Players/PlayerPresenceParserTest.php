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

    /**
     * Newer Paper builds route the join/leave broadcast through the system-chat path, so the line
     * reads "System chat: Steve joined the game". These were observed on a real server in this
     * fork's own dev environment and matched nothing: the join was rescued by the entity-id
     * signal, but the leave had no second signal at all, so players stayed online forever.
     */
    public function testSystemChatPrefixedBroadcastsAreDetected(): void
    {
        $join = PlayerPresenceParser::parse('[10:32:14 INFO]: System chat: JsemOlik joined the game', false);
        $this->assertSame(['event' => 'join', 'player' => 'JsemOlik'], $join);

        $leave = PlayerPresenceParser::parse('[10:40:00 INFO]: System chat: JsemOlik left the game', false);
        $this->assertSame(['event' => 'leave', 'player' => 'JsemOlik'], $leave);
    }

    /**
     * The leave-side counterpart to the entity-id join signal: emitted on the network thread
     * regardless of how the chat broadcast is decorated or whether it is suppressed.
     */
    public function testLostConnectionIsDetectedAsALeave(): void
    {
        $result = PlayerPresenceParser::parse('[10:40:00 INFO]: JsemOlik lost connection: Disconnected', false);

        $this->assertSame(['event' => 'leave', 'player' => 'JsemOlik'], $result);
    }

    /**
     * Stripping the system-chat prefix must not turn ordinary chat into a presence event — a child
     * typing "joined the game" should never register as a join.
     */
    public function testSystemChatPrefixedPlayerChatIsStillIgnored(): void
    {
        $this->assertNull(PlayerPresenceParser::parse('[10:40:00 INFO]: System chat: <JsemOlik> hello there', false));
        $this->assertNull(PlayerPresenceParser::parse('[10:40:00 INFO]: System chat: <JsemOlik> joined the game', false));
    }
}
