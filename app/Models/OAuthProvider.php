<?php

namespace Pterodactyl\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An OAuth 2 provider users can sign in with, configured from the admin area.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $color
 * @property bool $use_primary_color
 * @property string|null $logo
 * @property bool $enabled
 * @property int $sort_order
 * @property string $client_id
 * @property string $client_secret
 * @property string $authorize_url
 * @property string $token_url
 * @property string $userinfo_url
 * @property string|null $scopes
 * @property string $token_auth_method
 * @property bool $use_pkce
 * @property string $identifier_field
 * @property string $email_field
 * @property string|null $username_field
 * @property string|null $first_name_field
 * @property string|null $last_name_field
 * @property bool $link_by_email
 * @property bool $allow_registration
 * @property string|null $allowed_domains
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Database\Eloquent\Collection|\Pterodactyl\Models\UserOAuthIdentity[] $identities
 */
class OAuthProvider extends Model
{
    public const TOKEN_AUTH_METHODS = ['client_secret_post', 'client_secret_basic'];

    /**
     * The default primary color, used to preview providers that follow the user's primary color.
     */
    public const DEFAULT_PRIMARY_COLOR = '#8a4cf5';

    protected $table = 'oauth_providers';

    protected $guarded = ['id', 'uuid', 'logo', 'created_at', 'updated_at'];

    protected $hidden = ['client_secret'];

    protected $attributes = [
        'color' => '#2563eb',
        'use_primary_color' => false,
        'enabled' => false,
        'sort_order' => 0,
        'token_auth_method' => 'client_secret_post',
        'use_pkce' => true,
        'identifier_field' => 'sub',
        'email_field' => 'email',
        'link_by_email' => false,
        'allow_registration' => false,
    ];

    protected $casts = [
        'client_secret' => 'encrypted',
        'use_primary_color' => 'boolean',
        'enabled' => 'boolean',
        'use_pkce' => 'boolean',
        'link_by_email' => 'boolean',
        'allow_registration' => 'boolean',
        'sort_order' => 'integer',
    ];

    public static array $validationRules = [
        'uuid' => 'required|string|size:36|unique:oauth_providers,uuid',
        'name' => 'required|string|between:1,191',
        'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        'use_primary_color' => 'boolean',
        'logo' => 'nullable|string|max:191',
        'enabled' => 'boolean',
        'sort_order' => 'integer|min:0',
        'client_id' => 'required|string|max:191',
        'client_secret' => 'required|string',
        'authorize_url' => 'required|url|max:191',
        'token_url' => 'required|url|max:191',
        'userinfo_url' => 'required|url|max:191',
        'scopes' => 'nullable|string|max:191',
        'token_auth_method' => 'required|string|in:client_secret_post,client_secret_basic',
        'use_pkce' => 'boolean',
        'identifier_field' => 'required|string|max:191',
        'email_field' => 'required|string|max:191',
        'username_field' => 'nullable|string|max:191',
        'first_name_field' => 'nullable|string|max:191',
        'last_name_field' => 'nullable|string|max:191',
        'link_by_email' => 'boolean',
        'allow_registration' => 'boolean',
        'allowed_domains' => 'nullable|string',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * The URL the provider sends users back to, which has to be registered with the provider.
     */
    public function getCallbackUrl(): string
    {
        return route('auth.oauth.callback', $this->uuid);
    }

    public function getLogoUrl(): ?string
    {
        if (is_null($this->logo)) {
            return null;
        }

        return route('auth.oauth.logo', ['provider' => $this->uuid, 'v' => $this->updated_at?->timestamp]);
    }

    /**
     * @return string[]
     */
    public function getScopes(): array
    {
        return array_values(array_filter(preg_split('/[\s,]+/', $this->scopes ?? '')));
    }

    /**
     * @return string[]
     */
    public function getAllowedDomains(): array
    {
        return array_values(array_filter(array_map(
            fn (string $domain) => Str::lower(ltrim(trim($domain), '@')),
            preg_split('/[\s,]+/', $this->allowed_domains ?? '')
        )));
    }

    /**
     * Determines if an email address belongs to one of the allowed domains, when any are set.
     */
    public function allowsEmail(string $email): bool
    {
        $domains = $this->getAllowedDomains();
        if (empty($domains)) {
            return true;
        }

        return in_array(Str::lower(Str::afterLast($email, '@')), $domains, true);
    }

    /**
     * The data the login page and account settings need to display the provider.
     */
    /**
     * The color shown for the provider in the admin area.
     */
    public function getDisplayColor(): string
    {
        return $this->use_primary_color ? self::DEFAULT_PRIMARY_COLOR : $this->color;
    }

    public function toPublicArray(): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            // The login page uses the viewer's primary color when there's no color.
            'color' => $this->use_primary_color ? null : $this->color,
            'logo' => $this->getLogoUrl(),
        ];
    }

    /**
     * @return HasMany<UserOAuthIdentity, $this>
     */
    public function identities(): HasMany
    {
        return $this->hasMany(UserOAuthIdentity::class, 'provider_id');
    }
}
