<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Ramsey\Uuid\Uuid;
use Illuminate\View\View;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\OAuthProvider;
use Illuminate\Support\Facades\Storage;
use Pterodactyl\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Pterodactyl\Http\Requests\Admin\OAuthProviderFormRequest;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

class AuthenticationController extends Controller
{
    public function __construct(private AlertsMessageBag $alert, private SettingsRepositoryInterface $settings)
    {
    }

    /**
     * Lists the OAuth providers and the password login setting.
     */
    public function index(): View
    {
        return view('admin.authentication.index', [
            'passwordLogin' => (bool) config('pterodactyl.auth.password_login', true),
            'providers' => OAuthProvider::query()->withCount('identities')->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function updatePasswordLogin(Request $request): RedirectResponse
    {
        $enabled = $request->validate(['password_login' => 'required|boolean'])['password_login'];

        if (!$enabled && !OAuthProvider::query()->where('enabled', true)->exists()) {
            $this->alert->danger('Enable at least one OAuth provider before turning off password login, otherwise nobody could sign in.')->flash();

            return redirect()->route('admin.authentication');
        }

        $this->settings->set('settings::pterodactyl:auth:password_login', $enabled ? 'true' : 'false');
        $this->alert->success('Password login has been ' . ($enabled ? 'enabled.' : 'disabled.'))->flash();

        return redirect()->route('admin.authentication');
    }

    public function create(): View
    {
        return view('admin.authentication.form', ['provider' => null]);
    }

    /**
     * @throws \Throwable
     */
    public function store(OAuthProviderFormRequest $request): RedirectResponse
    {
        $provider = new OAuthProvider();
        $provider->forceFill(array_merge($request->providerData(), ['uuid' => Uuid::uuid4()->toString()]));
        $this->saveLogo($request, $provider);
        $provider->saveOrFail();

        $this->alert->success('The OAuth provider was created. Register the callback URL below with the provider before enabling it.')->flash();

        return redirect()->route('admin.authentication.view', $provider->id);
    }

    public function view(OAuthProvider $provider): View
    {
        return view('admin.authentication.form', ['provider' => $provider]);
    }

    /**
     * Serves the logo of a provider, including disabled ones, for the preview.
     */
    public function logo(OAuthProvider $provider): StreamedResponse
    {
        abort_if(is_null($provider->logo) || !Storage::disk()->exists($provider->logo), 404);

        return Storage::disk()->response($provider->logo, null, [
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function update(OAuthProviderFormRequest $request, OAuthProvider $provider): RedirectResponse
    {
        $provider->forceFill($request->providerData());

        if ($provider->isDirty('enabled') && !$provider->enabled && $this->wouldLockOut($provider)) {
            $this->alert->danger('This is the only enabled OAuth provider and password login is disabled. Enable password login first.')->flash();

            return redirect()->route('admin.authentication.view', $provider->id)->withInput();
        }

        $this->saveLogo($request, $provider);
        // Change the timestamp so a new logo isn't served from the browser cache.
        $provider->updated_at = now();
        $provider->saveOrFail();

        $this->alert->success('The OAuth provider was updated.')->flash();

        return redirect()->route('admin.authentication.view', $provider->id);
    }

    public function delete(OAuthProvider $provider): RedirectResponse
    {
        if ($provider->enabled && $this->wouldLockOut($provider)) {
            $this->alert->danger('This is the only enabled OAuth provider and password login is disabled. Enable password login first.')->flash();

            return redirect()->route('admin.authentication.view', $provider->id);
        }

        if ($provider->logo) {
            Storage::disk()->delete($provider->logo);
        }

        $provider->delete();
        $this->alert->success('The OAuth provider was deleted, along with the accounts linked to it.')->flash();

        return redirect()->route('admin.authentication');
    }

    /**
     * Determines if nobody could sign in once this provider stops being available.
     */
    private function wouldLockOut(OAuthProvider $provider): bool
    {
        return !config('pterodactyl.auth.password_login', true)
            && !OAuthProvider::query()->where('enabled', true)->whereKeyNot($provider->id)->exists();
    }

    private function saveLogo(OAuthProviderFormRequest $request, OAuthProvider $provider): void
    {
        $file = $request->file('logo_file');
        if (is_null($file) && !$request->boolean('remove_logo')) {
            return;
        }

        if ($provider->logo) {
            Storage::disk()->delete($provider->logo);
            $provider->logo = null;
        }

        if (!is_null($file)) {
            $name = sprintf('%s-%s.%s', $provider->uuid, Str::lower(Str::random(8)), Str::lower($file->getClientOriginalExtension()));
            $provider->logo = $file->storeAs('oauth-logos', $name);
        }
    }
}
