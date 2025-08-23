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
                // Override app name
                $appName = SettingsHelper::get('system', 'app_name');
                if ($appName) {
                    config(['app.name' => $appName]);
                }

                // Override timezone
                $timezone = SettingsHelper::get('system', 'timezone');
                if ($timezone) {
                    config(['app.timezone' => $timezone]);
                    date_default_timezone_set($timezone);
                }

                // Override pagination
                $pagination = SettingsHelper::globalPagination();
                if ($pagination) {
                    config(['app.pagination.default' => $pagination]);
                }

                // Override mail settings if they exist
                $this->overrideMailConfig();
                
                // Override other system settings
                $this->overrideSystemConfig();
            }
        } catch (\Exception $e) {
            // Fail silently if settings cannot be loaded
            logger('Settings override failed: ' . $e->getMessage());
        }
    }

    /**
     * Override mail configuration with settings
     */
    protected function overrideMailConfig(): void
    {
        $mailSettings = [
            'mail.from.name' => SettingsHelper::get('email', 'from_name'),
            'mail.from.address' => SettingsHelper::get('email', 'from_address'),
            'mail.mailers.smtp.host' => SettingsHelper::get('email', 'smtp_host'),
            'mail.mailers.smtp.port' => SettingsHelper::get('email', 'smtp_port'),
            'mail.mailers.smtp.username' => SettingsHelper::get('email', 'smtp_username'),
            'mail.mailers.smtp.password' => SettingsHelper::get('email', 'smtp_password'),
            'mail.mailers.smtp.encryption' => SettingsHelper::get('email', 'smtp_encryption'),
        ];

        foreach ($mailSettings as $configKey => $value) {
            if ($value !== null) {
                config([$configKey => $value]);
            }
        }
    }

    /**
     * Override other system configurations with settings
     */
    protected function overrideSystemConfig(): void
    {
        $systemSettings = [
            'session.lifetime' => SettingsHelper::get('security', 'session_timeout'),
            'auth.passwords.users.expire' => SettingsHelper::get('security', 'password_reset_expire', 60),
            'filesystems.default' => SettingsHelper::get('system', 'default_filesystem'),
        ];

        foreach ($systemSettings as $configKey => $value) {
            if ($value !== null) {
                config([$configKey => $value]);
            }
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