<?php

namespace Pterodactyl\Tests\Integration\Providers;

use Pterodactyl\Providers\SettingsServiceProvider;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

class SettingsServiceProviderTest extends IntegrationTestCase
{
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();

        $settings = $this->app->make(SettingsRepositoryInterface::class);
        $settings->set('settings::pterodactyl:auth:password_login', 'false');
        $settings->set('settings::app:name', 'Stored Name');
    }

    public function testDatabaseSettingsAreUsedByDefault(): void
    {
        config()->set('pterodactyl.load_environment_only', false);

        $this->bootProvider();

        $this->assertFalse(config('pterodactyl.auth.password_login'));
        $this->assertSame('Stored Name', config('app.name'));
    }

    /**
     * Password login is only changed from the Panel, so it has to be read from the database even
     * when the environment file is meant to be the only source of settings.
     */
    public function testPasswordLoginIsStillLoadedWhenOnlyUsingTheEnvironment(): void
    {
        config()->set('pterodactyl.load_environment_only', true);
        config()->set('app.name', 'Environment Name');

        $this->bootProvider();

        $this->assertFalse(config('pterodactyl.auth.password_login'));
        $this->assertSame('Environment Name', config('app.name'));
    }

    private function bootProvider(): void
    {
        $this->app->register(SettingsServiceProvider::class, true);
    }
}
