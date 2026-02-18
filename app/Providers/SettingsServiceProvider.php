<?php

namespace App\Providers;

use App\Helpers\SettingsHelper;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register SettingsHelper as singleton
        $this->app->singleton('settings', function ($app) {
            return new SettingsHelper();
        });

        // Register alias for easier access
        $this->app->alias('settings', SettingsHelper::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Override Laravel config with database settings if needed
        $this->overrideConfig();
    }

    /**
     * Override Laravel configuration with database settings
     */
    protected function overrideConfig(): void
    {
        try {
            // Only override if database is available and settings table exists
            if ($this->app->hasBeenBootstrapped() && $this->settingsTableExists()) {
                // Load ALL settings in a single query
                $allSettings = \App\Models\Setting::all()
                    ->groupBy('group_name')
                    ->map(fn ($group) => $group->mapWithKeys(fn ($s) => [$s->key_name => $s->getCastValue()]))
                    ->toArray();

                $get = fn (string $group, string $key, $default = null) =>
                    $allSettings[$group][$key] ?? $default;

                // Override app settings
                $appName = $get('system', 'app_name');
                if ($appName) {
                    config(['app.name' => $appName]);
                }

                $timezone = $get('system', 'timezone');
                if ($timezone) {
                    config(['app.timezone' => $timezone]);
                    date_default_timezone_set($timezone);
                }

                $pagination = $get('system', 'global_pagination');
                if ($pagination) {
                    config(['app.pagination.default' => $pagination]);
                }

                // Override mail settings
                $mailMap = [
                    'mail.from.name' => $get('email', 'from_name'),
                    'mail.from.address' => $get('email', 'from_address'),
                    'mail.mailers.smtp.host' => $get('email', 'smtp_host'),
                    'mail.mailers.smtp.port' => $get('email', 'smtp_port'),
                    'mail.mailers.smtp.username' => $get('email', 'smtp_username'),
                    'mail.mailers.smtp.password' => $get('email', 'smtp_password'),
                    'mail.mailers.smtp.encryption' => $get('email', 'smtp_encryption'),
                ];

                foreach ($mailMap as $configKey => $value) {
                    if ($value !== null) {
                        config([$configKey => $value]);
                    }
                }

                // Override system settings
                $systemMap = [
                    'session.lifetime' => $get('security', 'session_timeout'),
                    'auth.passwords.users.expire' => $get('security', 'password_reset_expire', 60),
                    'filesystems.default' => $get('system', 'default_filesystem'),
                ];

                foreach ($systemMap as $configKey => $value) {
                    if ($value !== null) {
                        config([$configKey => $value]);
                    }
                }
            }
        } catch (\Exception $e) {
            // Fail silently if settings cannot be loaded
            logger('Settings override failed: ' . $e->getMessage());
        }
    }

    /**
     * Check if settings table exists
     */
    protected function settingsTableExists(): bool
    {
        try {
            return \Schema::hasTable('settings');
        } catch (\Exception $e) {
            return false;
        }
    }
}