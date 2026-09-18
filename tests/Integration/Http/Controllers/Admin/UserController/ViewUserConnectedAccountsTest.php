<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin\UserController;

use Ramsey\Uuid\Uuid;
use Pterodactyl\Models\User;
use Pterodactyl\Models\OAuthProvider;
use Pterodactyl\Models\UserOAuthIdentity;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;

/**
 * The admin user page is rendered here rather than only POSTed to, because a mistake in the blade
 * template — a renamed route, a null dereference on an unlinked provider — throws only when the
 * view is actually built. Every other test in this directory exercises an endpoint and would stay
 * green while the page itself was a 500.
 */
class ViewUserConnectedAccountsTest extends HttpTestCase
{
    protected function tearDown(): void
    {
        UserOAuthIdentity::query()->delete();
        OAuthProvider::query()->delete();

        parent::tearDown();
    }

    private function createProvider(array $attributes = []): OAuthProvider
    {
        // forceFill rather than create(): `uuid` is in the model's $guarded list, so mass
        // assignment drops it and validation then rejects the row for a missing uuid.
        $provider = new OAuthProvider();
        $provider->forceFill($attributes + [
            'uuid' => Uuid::uuid4()->toString(),
            'name' => 'Test Provider',
            'color' => '#2563eb',
            'enabled' => true,
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'authorize_url' => 'https://example.com/authorize',
            'token_url' => 'https://example.com/token',
            'userinfo_url' => 'https://example.com/userinfo',
        ])->save();

        return $provider;
    }

    public function testPageRendersWhenTheUserHasLinkedAnAccount(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $provider = $this->createProvider(['name' => 'Acme SSO']);

        UserOAuthIdentity::query()->create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'provider_user_id' => 'remote-id-123',
            'email' => 'linked@example.com',
        ]);

        $this->actingAs($admin)
            ->get('/admin/users/view/' . $user->id)
            ->assertOk()
            ->assertSee('Connected Accounts')
            ->assertSee('Acme SSO')
            ->assertSee('Linked')
            ->assertSee('linked@example.com')
            ->assertSee('remote-id-123');
    }

    /**
     * The case the page exists for: a configured provider the user has NOT linked. Driving the
     * view off the identities alone would render nothing here, which is precisely the state an
     * admin is looking at the page to diagnose.
     */
    public function testPageListsAConfiguredProviderTheUserHasNotLinked(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $this->createProvider(['name' => 'Unlinked SSO']);

        $this->actingAs($admin)
            ->get('/admin/users/view/' . $user->id)
            ->assertOk()
            ->assertSee('Unlinked SSO')
            ->assertSee('Not linked');
    }

    /**
     * A disabled provider keeps any existing links, so it must still be listed and marked, rather
     * than silently disappearing and reading as "never linked".
     */
    public function testDisabledProviderIsStillListedAndMarked(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $this->createProvider(['name' => 'Retired SSO', 'enabled' => false]);

        $this->actingAs($admin)
            ->get('/admin/users/view/' . $user->id)
            ->assertOk()
            ->assertSee('Retired SSO')
            ->assertSee('Disabled');
    }

    public function testPageRendersWithNoProvidersConfigured(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->get('/admin/users/view/' . $user->id)
            ->assertOk()
            ->assertSee('No OAuth providers are configured.');
    }
}
