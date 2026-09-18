<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\User;
use Pterodactyl\Models\EggConsoleShortcut;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class EggConsoleShortcutControllerTest extends HttpTestCase
{
    use DatabaseTransactions;

    private Egg $egg;

    public function setUp(): void
    {
        parent::setUp();

        $this->egg = $this->cloneEggAndVariables(Egg::query()->firstOrFail());
    }

    public function testNonAdminCannotManageShortcuts(): void
    {
        $this->actingAs(User::factory()->create())
            ->post($this->route(), ['name' => 'Say', 'command' => 'say hello'])
            ->assertForbidden();
    }

    public function testShortcutWithoutArgumentsCanBeCreated(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post($this->route(), ['name' => 'Save', 'description' => 'Saves the world.', 'command' => 'save-all', 'sort_order' => 3])
            ->assertSessionHasNoErrors()
            ->assertRedirect($this->route());

        /** @var EggConsoleShortcut $shortcut */
        $shortcut = $this->egg->consoleShortcuts()->firstOrFail();
        $this->assertSame('Save', $shortcut->name);
        $this->assertSame('Saves the world.', $shortcut->description);
        $this->assertSame('save-all', $shortcut->command);
        $this->assertSame(3, $shortcut->sort_order);
        $this->assertSame([], $shortcut->arguments);
    }

    public function testShortcutWithArgumentsCanBeCreated(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post($this->route(), [
                'name' => 'Kick',
                'command' => 'kick {{ player }} {{reason}}',
                'arguments' => [
                    ['key' => 'player', 'label' => 'Player', 'placeholder' => 'Steve', 'required' => '1'],
                    ['key' => 'reason', 'label' => 'Reason', 'default' => 'Bye', 'description' => 'Shown to the player.'],
                ],
            ])
            ->assertSessionHasNoErrors();

        /** @var EggConsoleShortcut $shortcut */
        $shortcut = $this->egg->consoleShortcuts()->firstOrFail();
        $this->assertEquals([
            [
                'key' => 'player',
                'label' => 'Player',
                'description' => null,
                'placeholder' => 'Steve',
                'default' => null,
                'required' => true,
            ],
            [
                'key' => 'reason',
                'label' => 'Reason',
                'description' => 'Shown to the player.',
                'placeholder' => null,
                'default' => 'Bye',
                'required' => false,
            ],
        ], $shortcut->arguments);
    }

    public function testCommandCannotUsePlaceholderWithoutAnArgument(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post($this->route(), [
                'name' => 'Kick',
                'command' => 'kick {{player}} {{reason}}',
                'arguments' => [['key' => 'player', 'label' => 'Player']],
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.meta.source_field', 'command');
    }

    public function testArgumentKeysMustBeUnique(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post($this->route(), [
                'name' => 'Kick',
                'command' => 'kick {{player}}',
                'arguments' => [
                    ['key' => 'player', 'label' => 'Player'],
                    ['key' => 'player', 'label' => 'Player again'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.meta.source_field', 'arguments.0.key');
    }

    public function testArgumentKeyCannotContainSpaces(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post($this->route(), [
                'name' => 'Kick',
                'command' => 'kick',
                'arguments' => [['key' => 'a player', 'label' => 'Player']],
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.meta.source_field', 'arguments.0.key');
    }

    public function testShortcutCanBeUpdated(): void
    {
        $shortcut = $this->createShortcut();

        $this->actingAs(User::factory()->admin()->create())
            ->patch($this->route('/' . $shortcut->id), ['name' => 'Save all', 'command' => 'save-all flush'])
            ->assertSessionHasNoErrors()
            ->assertRedirect($this->route());

        $shortcut->refresh();
        $this->assertSame('Save all', $shortcut->name);
        $this->assertSame('save-all flush', $shortcut->command);
    }

    // Each of these makes a single request, because the exception handler rolls back open
    // transactions, which includes the one wrapping the test.
    public function testShortcutOfAnotherEggCannotBeUpdated(): void
    {
        $shortcut = $this->createShortcut(['egg_id' => $this->cloneEggAndVariables($this->egg)->id]);

        $this->actingAs(User::factory()->admin()->create())
            ->patch($this->route('/' . $shortcut->id), ['name' => 'Renamed', 'command' => 'save-all'])
            ->assertNotFound();
    }

    public function testShortcutOfAnotherEggCannotBeDeleted(): void
    {
        $shortcut = $this->createShortcut(['egg_id' => $this->cloneEggAndVariables($this->egg)->id]);

        $this->actingAs(User::factory()->admin()->create())
            ->delete($this->route('/' . $shortcut->id))
            ->assertNotFound();
    }

    public function testShortcutCanBeDeleted(): void
    {
        $shortcut = $this->createShortcut();

        $this->actingAs(User::factory()->admin()->create())
            ->delete($this->route('/' . $shortcut->id))
            ->assertRedirect($this->route());

        $this->assertDatabaseMissing('egg_console_shortcuts', ['id' => $shortcut->id]);
    }

    private function createShortcut(array $attributes = []): EggConsoleShortcut
    {
        $shortcut = new EggConsoleShortcut();
        $shortcut->forceFill(array_merge([
            'egg_id' => $this->egg->id,
            'name' => 'Save',
            'command' => 'save-all',
            'arguments' => [],
        ], $attributes))->saveOrFail();

        return $shortcut;
    }

    private function route(string $append = ''): string
    {
        return '/admin/nests/egg/' . $this->egg->id . '/shortcuts' . $append;
    }
}
