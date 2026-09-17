<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Auth;

use Ramsey\Uuid\Uuid;
use Pterodactyl\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Facades\Event;
use Pterodactyl\Models\OAuthProvider;
use Illuminate\Support\Facades\Session;
use Pterodactyl\Events\Auth\DirectLogin;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class OAuthControllerTest extends HttpTestCase
{
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();

        Event::fake([DirectLogin::class]);
    }

    public function testRedirectSendsUserToProviderWithStateAndPkce(): void
    {
        $provider = $this->createProvider();

        $response = $this->get(route('auth.oauth.redirect', $provider->uuid))->assertRedirect();

        $pending = Session::get('oauth_request');
        $this->assertSame($provider->uuid, $pending['provider']);
        $this->assertNull($pending['link_user_id']);

        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://sso.example.com/authorize?', $location);
        $this->assertStringContainsString('state=' . $pending['state'], $location);
        $this->assertStringContainsString('code_challenge_method=S256', $location);
        $this->assertStringContainsString('redirect_uri=' . rawurlencode($provider->getCallbackUrl()), $location);
    }

    public function testDisabledProviderIsNotFound(): void
    {
        $provider = $this->createProvider(['enabled' => false]);

        $this->get(route('auth.oauth.redirect', $provider->uuid))->assertNotFound();
    }

    public function testLinkedUserCanSignIn(): void
    {
        $provider = $this->createProvider();
        $user = User::factory()->create();
        $user->oauthIdentities()->create(['provider_id' => $provider->id, 'provider_user_id' => 'abc']);

        $this->fakeProvider(['sub' => 'abc', 'email' => 'someone@example.com']);

        $this->completeLogin($provider)->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->oauthIdentities()->first()->last_used_at);
        Event::assertDispatched(fn (DirectLogin $event) => $event->user->is($user));
    }

    public function testUserWithTwoFactorIsSentToCheckpoint(): void
    {
        $provider = $this->createProvider();
        $user = User::factory()->create(['use_totp' => true, 'totp_secret' => encrypt(str_repeat('a', 16))]);
        $user->oauthIdentities()->create(['provider_id' => $provider->id, 'provider_user_id' => 'abc']);

        $this->fakeProvider(['sub' => 'abc']);

        $response = $this->completeLogin($provider);

        $this->assertGuest();
        $token = Session::get('auth_confirmation_token');
        $this->assertSame($user->id, $token['user_id']);
        $response->assertRedirect('/auth/login?checkpoint=' . $token['token_value']);
    }

    public function testUnknownAccountIsRejectedByDefault(): void
    {
        $provider = $this->createProvider();
        User::factory()->create(['email' => 'someone@example.com']);

        $this->fakeProvider(['sub' => 'abc', 'email' => 'someone@example.com']);

        $this->completeLogin($provider)->assertRedirect('/auth/login?oauth_error=not_linked');
        $this->assertGuest();
    }

    public function testAccountIsLinkedByEmailWhenEnabled(): void
    {
        $provider = $this->createProvider(['link_by_email' => true]);
        $user = User::factory()->create(['email' => 'someone@example.com']);

        $this->fakeProvider(['sub' => 'abc', 'email' => 'Someone@example.com']);

        $this->completeLogin($provider)->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('user_oauth_identities', ['user_id' => $user->id, 'provider_user_id' => 'abc']);
    }

    public function testUnverifiedEmailIsNotLinked(): void
    {
        $provider = $this->createProvider(['link_by_email' => true]);
        User::factory()->create(['email' => 'someone@example.com']);

        $this->fakeProvider(['sub' => 'abc', 'email' => 'someone@example.com', 'email_verified' => false]);

        $this->completeLogin($provider)->assertRedirect('/auth/login?oauth_error=email_unverified');
        $this->assertGuest();
    }

    public function testUserIsCreatedWhenRegistrationIsAllowed(): void
    {
        $provider = $this->createProvider(['allow_registration' => true, 'allowed_domains' => '4camps.cz']);

        $this->fakeProvider([
            'sub' => 'abc',
            'email' => 'jan.novak@4camps.cz',
            'preferred_username' => 'Jan Novák',
            'given_name' => 'Jan',
            'family_name' => 'Novák',
        ]);

        $this->completeLogin($provider)->assertRedirect('/');

        /** @var User $user */
        $user = User::query()->where('email', 'jan.novak@4camps.cz')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('jan-novak', $user->username);
        $this->assertSame('Jan', $user->name_first);
        $this->assertSame('Novák', $user->name_last);
        $this->assertFalse($user->root_admin);
    }

    public function testRegistrationRespectsAllowedDomains(): void
    {
        $provider = $this->createProvider(['allow_registration' => true, 'allowed_domains' => '4camps.cz']);

        $this->fakeProvider(['sub' => 'abc', 'email' => 'someone@example.com']);

        $this->completeLogin($provider)->assertRedirect('/auth/login?oauth_error=domain');
        $this->assertDatabaseMissing('users', ['email' => 'someone@example.com']);
    }

    public function testInvalidStateIsRejected(): void
    {
        $provider = $this->createProvider();
        Http::fake();

        $this->withSession(['oauth_request' => $this->pending($provider)])
            ->get(route('auth.oauth.callback', ['provider' => $provider->uuid, 'code' => 'code', 'state' => 'wrong']))
            ->assertRedirect('/auth/login?oauth_error=state');

        Http::assertNothingSent();
    }

    public function testSignedInUserCanLinkProvider(): void
    {
        $provider = $this->createProvider();
        $user = User::factory()->create();

        $this->fakeProvider(['sub' => 'abc', 'email' => 'other@example.com']);

        $this->actingAs($user)
            ->withSession(['oauth_request' => $this->pending($provider, $user)])
            ->get(route('auth.oauth.callback', ['provider' => $provider->uuid, 'code' => 'code', 'state' => 'state']))
            ->assertRedirect('/account/linked-accounts?oauth=linked');

        $this->assertDatabaseHas('user_oauth_identities', ['user_id' => $user->id, 'provider_user_id' => 'abc', 'email' => 'other@example.com']);
    }

    public function testAccountLinkedToAnotherUserCannotBeLinked(): void
    {
        $provider = $this->createProvider();
        $owner = User::factory()->create();
        $owner->oauthIdentities()->create(['provider_id' => $provider->id, 'provider_user_id' => 'abc']);
        $user = User::factory()->create();

        $this->fakeProvider(['sub' => 'abc']);

        $this->actingAs($user)
            ->withSession(['oauth_request' => $this->pending($provider, $user)])
            ->get(route('auth.oauth.callback', ['provider' => $provider->uuid, 'code' => 'code', 'state' => 'state']))
            ->assertRedirect('/account/linked-accounts?oauth_error=already_linked');

        $this->assertSame(0, $user->oauthIdentities()->count());
    }

    public function testPasswordLoginCanBeDisabled(): void
    {
        config()->set('pterodactyl.auth.password_login', false);

        $this->postJson('/auth/login', ['user' => 'test', 'password' => 'password'])->assertForbidden();
        $this->postJson('/auth/password', ['email' => 'test@example.com'])->assertForbidden();
    }

    private function completeLogin(OAuthProvider $provider): TestResponse
    {
        return $this->withSession(['oauth_request' => $this->pending($provider)])
            ->get(route('auth.oauth.callback', ['provider' => $provider->uuid, 'code' => 'code', 'state' => 'state']));
    }

    private function pending(OAuthProvider $provider, ?User $user = null): array
    {
        return [
            'provider' => $provider->uuid,
            'state' => 'state',
            'verifier' => 'verifier',
            'link_user_id' => $user?->id,
            'expires_at' => now()->addMinutes(5)->timestamp,
        ];
    }

    private function fakeProvider(array $user): void
    {
        Http::fake([
            'sso.example.com/token' => Http::response(['access_token' => 'access-token', 'token_type' => 'Bearer']),
            'sso.example.com/userinfo' => Http::response($user),
        ]);
    }

    private function createProvider(array $attributes = []): OAuthProvider
    {
        $provider = new OAuthProvider();
        $provider->forceFill(array_merge([
            'uuid' => Uuid::uuid4()->toString(),
            'name' => 'SSO',
            'enabled' => true,
            'client_id' => 'client',
            'client_secret' => 'secret',
            'authorize_url' => 'https://sso.example.com/authorize',
            'token_url' => 'https://sso.example.com/token',
            'userinfo_url' => 'https://sso.example.com/userinfo',
            'scopes' => 'openid email profile',
            'username_field' => 'preferred_username',
            'first_name_field' => 'given_name',
            'last_name_field' => 'family_name',
        ], $attributes))->saveOrFail();

        return $provider->refresh();
    }
}
