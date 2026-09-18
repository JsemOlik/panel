<?php

namespace Pterodactyl\Providers;

use Laravel\Sanctum\Sanctum;
use Pterodactyl\Models\Area;
use Pterodactyl\Models\ApiKey;
use Pterodactyl\Models\Server;
use Pterodactyl\Policies\AreaPolicy;
use Pterodactyl\Policies\ServerPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     */
    protected $policies = [
        Server::class => ServerPolicy::class,
        Area::class => AreaPolicy::class,
    ];

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(ApiKey::class);
    }
}
