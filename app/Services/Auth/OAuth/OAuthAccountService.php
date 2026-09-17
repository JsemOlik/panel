<?php

namespace Pterodactyl\Services\Auth\OAuth;

use Ramsey\Uuid\Uuid;
use Illuminate\Support\Str;
use Pterodactyl\Models\User;
use Pterodactyl\Rules\Username;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\OAuthProvider;
use Illuminate\Contracts\Hashing\Hasher;
use Pterodactyl\Models\UserOAuthIdentity;
use Illuminate\Database\ConnectionInterface;
use Pterodactyl\Exceptions\Auth\OAuthException;

/**
 * Connects the accounts users have at OAuth providers with their Panel accounts.
 */
class OAuthAccountService
{
    public function __construct(private ConnectionInterface $connection, private Hasher $hasher)
    {
    }

    /**
     * Finds the Panel user signing in with an OAuth account. Depending on the provider's settings an
     * unknown account is linked to the user with the same email address, or a new user is created.
     *
     * @throws OAuthException
     */
    public function resolve(OAuthProvider $provider, OAuthUser $account): User
    {
        $identity = $this->findIdentity($provider, $account);
        if (!is_null($identity)) {
            $identity->update(['email' => $account->email, 'last_used_at' => now()]);

            return $identity->user;
        }

        if (!$provider->link_by_email && !$provider->allow_registration) {
            throw new OAuthException(OAuthException::NOT_LINKED);
        }

        // Linking and registration both rely on the email address, so it has to be trustworthy.
        if (is_null($account->email) || !filter_var($account->email, FILTER_VALIDATE_EMAIL)) {
            throw new OAuthException(OAuthException::NOT_LINKED, 'The provider did not return a valid email address.');
        }

        if ($account->emailVerified === false) {
            throw new OAuthException(OAuthException::EMAIL_NOT_VERIFIED);
        }

        if (!$provider->allowsEmail($account->email)) {
            throw new OAuthException(OAuthException::DOMAIN_NOT_ALLOWED);
        }

        /** @var User|null $user */
        $user = User::query()->where('email', Str::lower($account->email))->first();

        if (!is_null($user)) {
            if (!$provider->link_by_email) {
                throw new OAuthException(OAuthException::NOT_LINKED, 'A user with this email exists, but linking by email is disabled.');
            }

            $this->link($user, $provider, $account);

            return $user;
        }

        if (!$provider->allow_registration) {
            throw new OAuthException(OAuthException::NOT_LINKED);
        }

        return $this->register($provider, $account);
    }

    /**
     * Links an OAuth account to a user.
     *
     * @throws OAuthException
     */
    public function link(User $user, OAuthProvider $provider, OAuthUser $account): UserOAuthIdentity
    {
        $identity = $this->findIdentity($provider, $account);
        if (!is_null($identity)) {
            if ($identity->user_id !== $user->id) {
                throw new OAuthException(OAuthException::ALREADY_LINKED, 'The OAuth account is linked to a different user.');
            }

            $identity->update(['email' => $account->email, 'last_used_at' => now()]);

            return $identity;
        }

        if ($user->oauthIdentities()->where('provider_id', $provider->id)->exists()) {
            throw new OAuthException(OAuthException::ALREADY_LINKED, 'The user already has a different account linked for this provider.');
        }

        /** @var UserOAuthIdentity $identity */
        $identity = $user->oauthIdentities()->create([
            'provider_id' => $provider->id,
            'provider_user_id' => $account->id,
            'email' => $account->email,
            'last_used_at' => now(),
        ]);

        Activity::event('user:oauth.link')
            ->actor($user)
            ->subject($user)
            ->property('provider', $provider->name)
            ->log();

        return $identity;
    }

    private function findIdentity(OAuthProvider $provider, OAuthUser $account): ?UserOAuthIdentity
    {
        /* @var UserOAuthIdentity|null */
        return UserOAuthIdentity::query()
            ->with('user')
            ->where('provider_id', $provider->id)
            ->where('provider_user_id', $account->id)
            ->first();
    }

    /**
     * @throws OAuthException
     */
    private function register(OAuthProvider $provider, OAuthUser $account): User
    {
        [$first, $last] = $this->names($account);

        try {
            return $this->connection->transaction(function () use ($provider, $account, $first, $last) {
                $user = new User();
                $user->forceFill([
                    'uuid' => Uuid::uuid4()->toString(),
                    'email' => Str::lower($account->email),
                    'username' => $this->username($account),
                    'name_first' => $first,
                    'name_last' => $last,
                    // The user signs in through the provider, a password can be set with a password reset.
                    'password' => $this->hasher->make(Str::random(64)),
                    'root_admin' => false,
                ])->saveOrFail();

                $this->link($user, $provider, $account);

                Activity::event('user:user.create')
                    ->actor($user)
                    ->subject($user)
                    ->property(['email' => $user->email, 'username' => $user->username, 'admin' => false])
                    ->log();

                return $user;
            });
        } catch (OAuthException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new OAuthException(OAuthException::REGISTRATION_FAILED, 'Failed to create a user for the OAuth account.', $exception);
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function names(OAuthUser $account): array
    {
        $full = is_string($account->raw['name'] ?? null) ? trim($account->raw['name']) : '';
        $parts = $full === '' ? [] : preg_split('/\s+/', $full, 2);

        $first = $account->firstName ?? $parts[0] ?? $account->username ?? Str::before($account->email, '@');
        $last = $account->lastName ?? $parts[1] ?? '-';

        return [Str::limit($first, 188, ''), Str::limit($last, 188, '')];
    }

    /**
     * Builds a unique username that passes the username rules from the account's details.
     */
    private function username(OAuthUser $account): string
    {
        $base = Str::lower(Str::ascii($account->username ?? Str::before($account->email, '@')));
        $base = trim(preg_replace('/[^a-z0-9_.-]+/', '-', $base), '_.-');
        $base = Str::limit($base, 180, '');

        if (strlen($base) < 3 || !preg_match(Username::VALIDATION_REGEX, $base)) {
            $base = 'user-' . Str::lower(Str::random(6));
        }

        $username = $base;
        for ($i = 2; User::query()->where('username', $username)->exists(); ++$i) {
            $username = $base . '-' . $i;
        }

        return $username;
    }
}
