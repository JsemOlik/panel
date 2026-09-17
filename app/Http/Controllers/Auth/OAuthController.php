<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Pterodactyl\Facades\Activity;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Event;
use Pterodactyl\Models\OAuthProvider;
use Illuminate\Support\Facades\Storage;
use Pterodactyl\Events\Auth\DirectLogin;
use Pterodactyl\Exceptions\Auth\OAuthException;
use Pterodactyl\Services\Auth\OAuth\OAuthClient;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Pterodactyl\Services\Auth\OAuth\OAuthAccountService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OAuthController extends AbstractLoginController
{
    private const SESSION_KEY = 'oauth_request';

    private const LINKED_ACCOUNTS_PATH = '/account/linked-accounts';

    public function __construct(private OAuthClient $client, private OAuthAccountService $accounts)
    {
        parent::__construct();
    }

    /**
     * Sends the user to the provider. Signed-in users who pass "?link=1" link the provider to their
     * account instead of signing in.
     */
    public function redirect(Request $request, OAuthProvider $provider): RedirectResponse
    {
        $this->assertEnabled($provider);

        $link = $request->boolean('link');
        if ($link !== !is_null($request->user())) {
            return redirect()->to($link ? '/auth/login' : '/');
        }

        $state = Str::random(40);
        $verifier = $provider->use_pkce ? Str::random(96) : null;

        $request->session()->put(self::SESSION_KEY, [
            'provider' => $provider->uuid,
            'state' => $state,
            'verifier' => $verifier,
            'link_user_id' => $link ? $request->user()->id : null,
            'expires_at' => CarbonImmutable::now()->addMinutes(10)->timestamp,
        ]);

        return redirect()->away($this->client->getAuthorizationUrl($provider, $state, $verifier));
    }

    /**
     * Handles the user returning from the provider.
     */
    public function callback(Request $request, OAuthProvider $provider): RedirectResponse
    {
        $pending = $request->session()->pull(self::SESSION_KEY);
        $linkUserId = is_array($pending) ? ($pending['link_user_id'] ?? null) : null;

        try {
            $this->assertEnabled($provider);
            $this->assertValidState($request, $provider, $pending);

            if ($request->filled('error')) {
                throw new OAuthException(OAuthException::DENIED, 'The provider returned an error: ' . $request->input('error'));
            }

            if (!is_string($code = $request->input('code')) || $code === '') {
                throw new OAuthException(OAuthException::PROVIDER_ERROR, 'The provider did not return an authorization code.');
            }

            $account = $this->client->getUser($provider, $code, $pending['verifier'] ?? null);

            if (!is_null($linkUserId)) {
                $this->accounts->link($request->user(), $provider, $account);

                return redirect()->to(self::LINKED_ACCOUNTS_PATH . '?oauth=linked');
            }

            return $this->signIn($request, $this->accounts->resolve($provider, $account));
        } catch (OAuthException $exception) {
            Log::info('OAuth ' . ($linkUserId ? 'link' : 'login') . " with provider \"{$provider->name}\" failed: {$exception->getMessage()}", [
                'reason' => $exception->reason,
                'exception' => $exception->getPrevious(),
            ]);

            if (is_null($linkUserId)) {
                Activity::event('auth:oauth.fail')
                    ->withRequestMetadata()
                    ->property(['provider' => $provider->name, 'reason' => $exception->reason])
                    ->log();
            }

            $path = $linkUserId && $request->user() ? self::LINKED_ACCOUNTS_PATH : '/auth/login';

            return redirect()->to($path . '?' . http_build_query(['oauth_error' => $exception->reason]));
        }
    }

    /**
     * Serves a provider's logo for the login page.
     */
    public function logo(OAuthProvider $provider): StreamedResponse
    {
        if (!$provider->enabled || is_null($provider->logo) || !Storage::disk()->exists($provider->logo)) {
            throw new NotFoundHttpException();
        }

        return Storage::disk()->response($provider->logo, null, [
            'Cache-Control' => 'public, max-age=604800',
            // Logos may be SVGs, which must never run scripts when opened directly.
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Signs the user in, or sends them to the two-factor checkpoint first.
     */
    private function signIn(Request $request, User $user): RedirectResponse
    {
        if ($user->use_totp) {
            Activity::event('auth:checkpoint')->withRequestMetadata()->subject($user)->log();

            $request->session()->put('auth_confirmation_token', [
                'user_id' => $user->id,
                'token_value' => $token = Str::random(64),
                'expires_at' => CarbonImmutable::now()->addMinutes(5),
            ]);

            return redirect()->to('/auth/login?' . http_build_query(['checkpoint' => $token]));
        }

        $request->session()->remove('auth_confirmation_token');
        $request->session()->regenerate();

        $this->auth->guard()->login($user, true);

        Event::dispatch(new DirectLogin($user, true));

        return redirect()->intended($this->redirectPath());
    }

    private function assertEnabled(OAuthProvider $provider): void
    {
        if (!$provider->enabled) {
            throw new NotFoundHttpException();
        }
    }

    /**
     * @throws OAuthException
     */
    private function assertValidState(Request $request, OAuthProvider $provider, mixed $pending): void
    {
        if (
            !is_array($pending)
            || ($pending['provider'] ?? null) !== $provider->uuid
            || ($pending['expires_at'] ?? 0) < CarbonImmutable::now()->timestamp
            || !is_string($request->input('state'))
            || !hash_equals($pending['state'] ?? '', $request->input('state'))
        ) {
            throw new OAuthException(OAuthException::INVALID_STATE, 'The OAuth state is missing, expired or does not match.');
        }

        // The account to link to must still be the one signed in.
        if (!is_null($pending['link_user_id']) && $request->user()?->id !== $pending['link_user_id']) {
            throw new OAuthException(OAuthException::INVALID_STATE, 'The signed-in user changed during the OAuth flow.');
        }
    }
}
