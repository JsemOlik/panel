<?php

namespace Pterodactyl\Console\Commands\User;

use Illuminate\Console\Command;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

class PasswordLoginCommand extends Command
{
    protected $description = 'Enable or disable signing in to the Panel with a password. Use this to regain access if an OAuth provider stops working.';

    protected $signature = 'p:auth:password-login {--enable : Allow signing in with a password.} {--disable : Only allow signing in with OAuth providers.}';

    public function __construct(private SettingsRepositoryInterface $settings)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('enable') === $this->option('disable')) {
            $this->line('Password login is currently ' . (config('pterodactyl.auth.password_login', true) ? 'enabled' : 'disabled') . '.');
            $this->line('Pass either --enable or --disable to change it.');

            return $this->option('enable') ? self::FAILURE : self::SUCCESS;
        }

        $enabled = (bool) $this->option('enable');
        $this->settings->set('settings::pterodactyl:auth:password_login', $enabled ? 'true' : 'false');

        $this->info('Password login has been ' . ($enabled ? 'enabled' : 'disabled') . '.');

        return self::SUCCESS;
    }
}
