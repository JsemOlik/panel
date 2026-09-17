<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Links a user to their account at an OAuth provider.
 *
 * @property int $id
 * @property int $user_id
 * @property int $provider_id
 * @property string $provider_user_id
 * @property string|null $email
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property User $user
 * @property OAuthProvider $provider
 */
class UserOAuthIdentity extends Model
{
    protected $table = 'user_oauth_identities';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'user_id' => 'integer',
        'provider_id' => 'integer',
        'last_used_at' => 'datetime',
    ];

    public static array $validationRules = [
        'user_id' => 'required|integer|exists:users,id',
        'provider_id' => 'required|integer|exists:oauth_providers,id',
        'provider_user_id' => 'required|string|max:191',
        'email' => 'nullable|string|max:191',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<OAuthProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(OAuthProvider::class, 'provider_id');
    }
}
