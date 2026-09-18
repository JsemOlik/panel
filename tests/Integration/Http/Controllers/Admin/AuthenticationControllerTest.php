<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Ramsey\Uuid\Uuid;
use Pterodactyl\Models\User;
use Pterodactyl\Models\OAuthProvider;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

class AuthenticationControllerTest extends HttpTestCase
{
    use DatabaseTransactions;

    public function testNonAdminCannotChangePasswordLogin(): void
    {
        $this->actingAs(User::factory()->create())
            ->patch('/admin/authentication', ['password_login' => 0])
            ->assertForbidden();
    }

    public function testPasswordLoginCannotBeDisabledWithoutAnEnabledProvider(): void
    {
        $this->createProvider(['enabled' => false]);

        $this->actingAs(User::factory()->admin()->create())
            ->patch('/admin/authentication', ['password_login' => 0])
            ->assertRedirect(route('admin.authentication'));

        $this->assertNull($this->setting());
    }

    public function testPasswordLoginCanBeDisabledWithAnEnabledProvider(): void
    {
        $this->createProvider();

        $this->actingAs(User::factory()->admin()->create())
            ->patch('/admin/authentication', ['password_login' => 0])
            ->assertRedirect(route('admin.authentication'));

        $this->assertSame('false', $this->setting());
    }

    public function testPasswordLoginCanBeEnabledAgain(): void
    {
        $this->setting('false');

        $this->actingAs(User::factory()->admin()->create())
            ->patch('/admin/authentication', ['password_login' => 1])
            ->assertRedirect(route('admin.authentication'));

        $this->assertSame('true', $this->setting());
    }

    public function testProviderCanBeCreatedWithThePrimaryColor(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/authentication/new', $this->providerData(['use_primary_color' => 1]))
            ->assertSessionHasNoErrors();

        /** @var OAuthProvider $provider */
        $provider = OAuthProvider::query()->where('name', 'SSO')->firstOrFail();
        $this->assertTrue($provider->use_primary_color);

        // The login page falls back to the viewer's own primary color when there is no color.
        $this->assertNull($provider->toPublicArray()['color']);
        $this->assertSame(OAuthProvider::DEFAULT_PRIMARY_COLOR, $provider->getDisplayColor());
    }

    public function testProviderKeepsItsOwnColorWhenThePrimaryColorIsNotUsed(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/authentication/new', $this->providerData(['color' => '#abcdef', 'use_primary_color' => 0]))
            ->assertSessionHasNoErrors();

        /** @var OAuthProvider $provider */
        $provider = OAuthProvider::query()->where('name', 'SSO')->firstOrFail();
        $this->assertFalse($provider->use_primary_color);
        $this->assertSame('#abcdef', $provider->toPublicArray()['color']);
        $this->assertSame('#abcdef', $provider->getDisplayColor());
    }

    public function testPrimaryColorCanBeTurnedOffWithoutLosingTheSavedColor(): void
    {
        $provider = $this->createProvider(['color' => '#abcdef', 'use_primary_color' => true]);

        $this->actingAs(User::factory()->admin()->create())
            ->patch('/admin/authentication/view/' . $provider->id, $this->providerData([
                'color' => '#abcdef',
                'use_primary_color' => 0,
                'enabled' => 1,
            ]))
            ->assertSessionHasNoErrors();

        $provider->refresh();
        $this->assertFalse($provider->use_primary_color);
        $this->assertSame('#abcdef', $provider->color);
    }

    private function providerData(array $attributes = []): array
    {
        return array_merge([
            'name' => 'SSO',
            'color' => '#2563eb',
            'use_primary_color' => 0,
            'enabled' => 0,
            'sort_order' => 0,
            'client_id' => 'client',
            'client_secret' => 'secret',
            'authorize_url' => 'https://sso.example.com/authorize',
            'token_url' => 'https://sso.example.com/token',
            'userinfo_url' => 'https://sso.example.com/userinfo',
            'token_auth_method' => 'client_secret_post',
            'use_pkce' => 1,
            'identifier_field' => 'sub',
            'email_field' => 'email',
            'link_by_email' => 0,
            'allow_registration' => 0,
        ], $attributes);
    }

    private function createProvider(array $attributes = []): OAuthProvider
    {
        $provider = new OAuthProvider();
        $provider->forceFill(array_merge($this->providerData([
            'uuid' => Uuid::uuid4()->toString(),
            'enabled' => true,
        ]), $attributes))->saveOrFail();

        return $provider->refresh();
    }

    /**
     * Reads the stored password login setting, or writes it when a value is given.
     */
    private function setting(?string $value = null): ?string
    {
        $settings = $this->app->make(SettingsRepositoryInterface::class);

        if (!is_null($value)) {
            $settings->set('settings::pterodactyl:auth:password_login', $value);
        }

        return $settings->get('settings::pterodactyl:auth:password_login');
    }
}
