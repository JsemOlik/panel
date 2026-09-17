<?php

namespace Pterodactyl\Http\Middleware;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Blocks the password login and password reset endpoints while password login is disabled.
 */
class RequirePasswordLogin
{
    public function handle(Request $request, \Closure $next): mixed
    {
        if (!config('pterodactyl.auth.password_login', true)) {
            throw new AccessDeniedHttpException('Přihlášení heslem je vypnuté. Přihlas se pomocí jedné z nabízených služeb.');
        }

        return $next($request);
    }
}
