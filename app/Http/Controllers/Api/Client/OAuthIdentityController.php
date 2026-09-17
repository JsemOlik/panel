<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\OAuthProvider;
use Pterodactyl\Models\UserOAuthIdentity;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class OAuthIdentityController extends ClientApiController
{
    /**
     * Lists the enabled OAuth providers and whether the user has linked each of them.
     */
    public function index(ClientApiRequest $request): JsonResponse
    {
        $identities = $request->user()->oauthIdentities()->get()->keyBy('provider_id');

        $data = OAuthProvider::query()
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (OAuthProvider $provider) use ($identities) {
                /** @var UserOAuthIdentity|null $identity */
                $identity = $identities->get($provider->id);

                return array_merge($provider->toPublicArray(), [
                    'linked' => !is_null($identity),
                    'email' => $identity?->email,
                    'linked_at' => $identity?->created_at?->toAtomString(),
                    'last_used_at' => $identity?->last_used_at?->toAtomString(),
                ]);
            });

        return new JsonResponse(['data' => $data, 'password_login' => (bool) config('pterodactyl.auth.password_login', true)]);
    }

    /**
     * Unlinks an OAuth provider from the user's account.
     *
     * @throws DisplayException
     */
    public function delete(ClientApiRequest $request, string $provider): JsonResponse
    {
        $user = $request->user();

        /** @var UserOAuthIdentity|null $identity */
        $identity = $user->oauthIdentities()
            ->whereHas('provider', fn ($query) => $query->where('uuid', $provider))
            ->with('provider')
            ->first();

        if (is_null($identity)) {
            return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
        }

        $remaining = $user->oauthIdentities()
            ->whereKeyNot($identity->id)
            ->whereHas('provider', fn ($query) => $query->where('enabled', true))
            ->exists();

        if (!config('pterodactyl.auth.password_login', true) && !$remaining) {
            throw new DisplayException('This is the only way you can sign in while password login is disabled, so it cannot be unlinked.');
        }

        $identity->delete();

        Activity::event('user:oauth.unlink')->property('provider', $identity->provider->name)->log();

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }
}
