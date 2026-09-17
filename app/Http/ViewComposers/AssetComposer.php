<?php

namespace Pterodactyl\Http\ViewComposers;

use Illuminate\View\View;
use Pterodactyl\Models\OAuthProvider;
use Illuminate\Database\QueryException;
use Pterodactyl\Services\Helpers\AssetHashService;

class AssetComposer
{
    /**
     * AssetComposer constructor.
     */
    public function __construct(private AssetHashService $assetHashService)
    {
    }

    /**
     * Provide access to the asset service in the views.
     */
    public function compose(View $view): void
    {
        $view->with('asset', $this->assetHashService);
        $view->with('siteConfiguration', [
            'name' => config('app.name') ?? 'Pterodactyl',
            'locale' => config('app.locale') ?? 'en',
            'version' => config('app.version'),
            'recaptcha' => [
                'enabled' => config('recaptcha.enabled', false),
                'siteKey' => config('recaptcha.website_key') ?? '',
            ],
            'auth' => [
                'passwordLogin' => (bool) config('pterodactyl.auth.password_login', true),
                'providers' => $this->providers(),
            ],
        ]);
    }

    /**
     * The OAuth providers users can sign in with.
     */
    private function providers(): array
    {
        try {
            return OAuthProvider::query()
                ->where('enabled', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (OAuthProvider $provider) => $provider->toPublicArray())
                ->all();
        } catch (QueryException) {
            // The table doesn't exist before the migrations have run.
            return [];
        }
    }
}
