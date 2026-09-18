<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Server;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\EggConsoleShortcut;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

class ConsoleShortcutTest extends ClientApiIntegrationTestCase
{
    /**
     * The shortcuts of the server's egg are returned to anyone who may use the console.
     */
    public function testShortcutsAreReturnedWithTheServer(): void
    {
        /** @var Server $server */
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_CONTROL_CONSOLE]);
        $server = $this->useOwnEgg($server);
        $this->createShortcut($server, [
            'name' => 'Kick',
            'description' => 'Kicks a player.',
            'command' => 'kick {{player}}',
            'arguments' => [['key' => 'player', 'label' => 'Player', 'description' => null, 'placeholder' => 'Steve', 'default' => null, 'required' => true]],
            'sort_order' => 0,
        ]);
        $this->createShortcut($server, ['name' => 'Save', 'command' => 'save-all', 'sort_order' => 1]);

        $response = $this->actingAs($user)->getJson($this->link($server))->assertOk();

        $shortcuts = $response->json('attributes.console_shortcuts');
        $this->assertCount(2, $shortcuts);
        // They come back in the order the egg defines them.
        $this->assertSame(['Kick', 'Save'], array_column($shortcuts, 'name'));
        $this->assertSame('kick {{player}}', $shortcuts[0]['command']);
        $this->assertSame('Kicks a player.', $shortcuts[0]['description']);
        $this->assertSame('Player', $shortcuts[0]['arguments'][0]['label']);
        $this->assertTrue($shortcuts[0]['arguments'][0]['required']);
        $this->assertSame([], $shortcuts[1]['arguments']);
    }

    public function testShortcutsAreHiddenWithoutTheConsolePermission(): void
    {
        /** @var Server $server */
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_WEBSOCKET_CONNECT]);
        $server = $this->useOwnEgg($server);
        $this->createShortcut($server, ['name' => 'Save', 'command' => 'save-all']);

        $this->actingAs($user)
            ->getJson($this->link($server))
            ->assertOk()
            ->assertJsonPath('attributes.console_shortcuts', []);
    }

    /**
     * Moves the server onto a copy of its egg, so the shortcuts of other tests aren't returned.
     */
    private function useOwnEgg(Server $server): Server
    {
        $server->update(['egg_id' => $this->cloneEggAndVariables($server->egg)->id]);

        return $server->refresh();
    }

    private function createShortcut(Server $server, array $attributes): EggConsoleShortcut
    {
        $shortcut = new EggConsoleShortcut();
        $shortcut->forceFill(array_merge(['egg_id' => $server->egg_id, 'arguments' => []], $attributes))->saveOrFail();

        return $shortcut;
    }
}
