<?php

namespace Pterodactyl\Services\Auth\OAuth;

/**
 * The user details returned by an OAuth provider, read through the provider's field mapping.
 */
final class OAuthUser
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $email,
        public readonly ?string $username,
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        // Null when the provider doesn't say whether the email address is verified.
        public readonly ?bool $emailVerified,
        public readonly array $raw,
    ) {
    }
}
