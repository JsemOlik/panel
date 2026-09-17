<?php

namespace Pterodactyl\Services\Auth\OAuth;

use Illuminate\Support\Arr;
use Pterodactyl\Models\OAuthProvider;
use Pterodactyl\Exceptions\Auth\OAuthException;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * A generic OAuth 2 client using the authorization code flow (with PKCE when enabled).
 */
class OAuthClient
{
    public function __construct(private HttpFactory $http)
    {
    }

    /**
     * Returns the provider's authorization URL the user is sent to.
     */
    public function getAuthorizationUrl(OAuthProvider $provider, string $state, ?string $verifier): string
    {
        $query = array_filter([
            'response_type' => 'code',
            'client_id' => $provider->client_id,
            'redirect_uri' => $provider->getCallbackUrl(),
            'scope' => implode(' ', $provider->getScopes()) ?: null,
            'state' => $state,
            'code_challenge' => $verifier ? self::challenge($verifier) : null,
            'code_challenge_method' => $verifier ? 'S256' : null,
        ]);

        $separator = str_contains($provider->authorize_url, '?') ? '&' : '?';

        return $provider->authorize_url . $separator . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Exchanges the authorization code for an access token and loads the user it belongs to.
     *
     * @throws OAuthException
     */
    public function getUser(OAuthProvider $provider, string $code, ?string $verifier): OAuthUser
    {
        $token = $this->getAccessToken($provider, $code, $verifier);

        try {
            $response = $this->request()->withToken($token)->get($provider->userinfo_url);
        } catch (\Throwable $exception) {
            throw new OAuthException(OAuthException::PROVIDER_ERROR, 'Failed to load the user from the provider.', $exception);
        }

        if (!$response->successful() || !is_array($data = $response->json())) {
            throw new OAuthException(OAuthException::PROVIDER_ERROR, sprintf('The userinfo endpoint responded with HTTP %d.', $response->status()));
        }

        $id = self::string($data, $provider->identifier_field);
        if (is_null($id)) {
            throw new OAuthException(OAuthException::PROVIDER_ERROR, sprintf('The userinfo response has no "%s" field, available fields: %s.', $provider->identifier_field, implode(', ', array_keys(Arr::dot($data))) ?: 'none'));
        }

        $verified = Arr::get($data, 'email_verified');

        return new OAuthUser(
            id: $id,
            email: self::string($data, $provider->email_field),
            username: self::string($data, $provider->username_field),
            firstName: self::string($data, $provider->first_name_field),
            lastName: self::string($data, $provider->last_name_field),
            emailVerified: is_null($verified) ? null : filter_var($verified, FILTER_VALIDATE_BOOLEAN),
            raw: $data,
        );
    }

    /**
     * @throws OAuthException
     */
    private function getAccessToken(OAuthProvider $provider, string $code, ?string $verifier): string
    {
        $body = array_filter([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $provider->getCallbackUrl(),
            'code_verifier' => $verifier,
        ]);

        $request = $this->request()->asForm();
        if ($provider->token_auth_method === 'client_secret_basic') {
            $request = $request->withBasicAuth(rawurlencode($provider->client_id), rawurlencode($provider->client_secret));
        } else {
            $body['client_id'] = $provider->client_id;
            $body['client_secret'] = $provider->client_secret;
        }

        try {
            $response = $request->post($provider->token_url, $body);
        } catch (\Throwable $exception) {
            throw new OAuthException(OAuthException::PROVIDER_ERROR, 'Failed to request an access token.', $exception);
        }

        $token = $response->json('access_token');
        if (!$response->successful() || !is_string($token) || $token === '') {
            throw new OAuthException(OAuthException::PROVIDER_ERROR, sprintf('The token endpoint responded with HTTP %d: %s', $response->status(), $response->json('error') ?? 'no access token'));
        }

        return $token;
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        return $this->http->acceptJson()
            ->connectTimeout(config('pterodactyl.guzzle.connect_timeout'))
            ->timeout(config('pterodactyl.guzzle.timeout'));
    }

    /**
     * Reads a field from the userinfo response, the path may use dot notation for nested fields.
     */
    private static function string(array $data, ?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        $value = Arr::get($data, $path);
        if (!is_scalar($value) || is_bool($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public static function challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }
}
