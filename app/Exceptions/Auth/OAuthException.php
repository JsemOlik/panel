<?php

namespace Pterodactyl\Exceptions\Auth;

/**
 * Thrown when an OAuth login or account link can't be completed. The reason is a short code
 * the frontend turns into a message for the user, the exception message is only logged.
 */
class OAuthException extends \Exception
{
    public const DENIED = 'denied';
    public const INVALID_STATE = 'state';
    public const PROVIDER_ERROR = 'provider';
    public const NOT_LINKED = 'not_linked';
    public const DOMAIN_NOT_ALLOWED = 'domain';
    public const EMAIL_NOT_VERIFIED = 'email_unverified';
    public const ALREADY_LINKED = 'already_linked';
    public const REGISTRATION_FAILED = 'registration';

    public function __construct(public readonly string $reason, string $message = '', ?\Throwable $previous = null)
    {
        parent::__construct($message ?: $reason, 0, $previous);
    }
}
