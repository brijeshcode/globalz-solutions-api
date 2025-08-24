<?php

namespace App\Helpers;

use App\Models\Setting;
use App\Models\UserSetting;
use Illuminate\Support\Facades\Auth;

class SettingsHelper
{
    /**
     * Get global setting with fallback chain
     * 
     * @param string $group
     * @param string $key
     * @param mixed $default
     * @param bool $autoCreate
     * @param string $dataType
     * @return mixed
     */
    public static function get(string $group, string $key, $default = null, $autoCreate = false, string $dataType = Setting::TYPE_STRING)
    {
        return Setting::get($group, $key, $default, $autoCreate, $dataType);
    }

    /**
     * Set global setting
     * 
     * @param string $group
     * @param string $key
     * @param mixed $value
     * @param string $dataType
     * @param string|null $description
     * @return Setting
     */
    public static function set(string $group, string $key, $value, string $dataType = Setting::TYPE_STRING, ?string $description = null): Setting
    {
        return Setting::set($group, $key, $value, $dataType, $description);
    }

    /**
     * Get user setting with global fallback
     * 
     * @param int|null $userId
     * @param string $key
     * @param mixed $default
     * @param string|null $globalGroup
     * @return mixed
     */
    public static function userGet(?int $userId, string $key, $default = null, ?string $globalGroup = 'system')
    {
        $userId = $userId ?? Auth::id();
        
        if (!$userId) {
            return $globalGroup ? self::get($globalGroup, $key, $default) : $default;
        }

        // Try user setting first
        $userValue = UserSetting::get($userId, $key, null);
        
        if ($userValue !== null) {
            return $userValue;
        }

        // Fallback to global setting if specified
        if ($globalGroup) {
            return self::get($globalGroup, $key, $default);
        }

        return $default;
    }

    /**
     * Set user setting
     * 
     * @param int|null $userId
     * @param string $key
     * @param mixed $value
     * @param string $dataType
     * @param string|null $description
     * @return UserSetting
     */
    public static function userSet(?int $userId, string $key, $value, string $dataType = UserSetting::TYPE_STRING, ?string $description = null): UserSetting
    {
        $userId = $userId ?? Auth::id();
        
        if (!$userId) {
            throw new \InvalidArgumentException('User ID is required to set user settings');
        }

        return UserSetting::set($userId, $key, $value, $dataType, $description);
    }

    /**
     * Get application configuration with settings override
     * 
     * @param string $configKey Laravel config key (e.g., 'app.name')
     * @param string $settingsGroup Settings group
     * @param string $settingsKey Settings key
     * @param mixed $default
     * @return mixed
     */
    public static function config(string $configKey, string $settingsGroup, string $settingsKey, $default = null)
    {
        // Try settings first, then Laravel config, then default
        $settingsValue = self::get($settingsGroup, $settingsKey, null);
        
        if ($settingsValue !== null) {
            return $settingsValue;
        }

        return config($configKey, $default);
    }

    /**
     * Quick access methods for common settings
     */
    public static function appName(): string
    {
        return self::config('app.name', 'system', 'app_name', 'Laravel');
    }

    public static function timezone(): string
    {
        return self::config('app.timezone', 'system', 'timezone', 'UTC');
    }

    public static function globalPagination(): int
    {
        return self::get('system', 'global_pagination', 25);
    }

    public static function itemsPagination(): int
    {
        return self::get('items', 'default_page_size', 15);
    }

    public static function suppliersPagination(): int
    {
        return self::get('suppliers', 'default_page_size', 20);
    }

    public static function currencySymbol(): string
    {
        return self::get('system', 'currency_symbol', '$');
    }

    public static function dateFormat(): string
    {
        return self::get('system', 'date_format', 'Y-m-d');
    }

    public static function decimalPlaces(): int
    {
        return self::get('financial', 'decimal_places', 2);
    }

    /**
     * User-specific quick access methods
     */
    public static function userTheme(?int $userId = null): string
    {
        return self::userGet($userId, 'theme', 'light');
    }

    public static function userLanguage(?int $userId = null): string
    {
        return self::userGet($userId, 'language', 'en');
    }

    public static function userTimezone(?int $userId = null): string
    {
        return self::userGet($userId, 'timezone', self::timezone());
    }

    public static function userLayout(?int $userId = null): string
    {
        return self::userGet($userId, 'layout', 'sidebar');
    }

    public static function userNotificationsEnabled(?int $userId = null): bool
    {
        return self::userGet($userId, 'notifications_enabled', true);
    }

    /**
     * Item code generation helpers
     */
    public static function getCurrentItemCode(): string
    {
        return (string) Setting::get('items', 'code_counter', 5000, true, Setting::TYPE_NUMBER);
    }

    public static function incrementItemCode(): int
    {
        return Setting::incrementValue('items', 'code_counter');
    }

    public static function getNextSupplierCode(): string
    {
        return (string) Setting::get('suppliers', 'code_counter', 1000, true, Setting::TYPE_NUMBER);
    }

    public static function incrementSupplierCode(): int
    {
        return Setting::incrementValue('suppliers', 'code_counter');
    }

    /**
     * Bulk operations
     */
    public static function getGroup(string $group): array
    {
        return Setting::getGroup($group);
    }

    public static function getAllUserSettings(?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        
        if (!$userId) {
            return [];
        }

        return UserSetting::getAllForUser($userId);
    }

    /**
     * Settings validation helpers
     */
    public static function isValidDataType(string $type): bool
    {
        return in_array($type, Setting::getDataTypes());
    }

    public static function getDataTypes(): array
    {
        return Setting::getDataTypes();
    }

    /**
     * Cache management
     */
    public static function clearCache(): void
    {
        Setting::clearCache();
    }

    /**
     * Feature flags / toggles
     */
    public static function isFeatureEnabled(string $feature): bool
    {
        return self::get('features', $feature, false);
    }

    public static function enableFeature(string $feature): Setting
    {
        return self::set('features', $feature, true, Setting::TYPE_BOOLEAN, "Feature toggle for {$feature}");
    }

    public static function disableFeature(string $feature): Setting
    {
        return self::set('features', $feature, false, Setting::TYPE_BOOLEAN, "Feature toggle for {$feature}");
    }

    /**
     * Environment-specific settings
     */
    public static function isProduction(): bool
    {
        return app()->environment('production');
    }

    public static function isDevelopment(): bool
    {
        return app()->environment('local', 'development');
    }

    public static function getEnvironmentSetting(string $group, string $key, $default = null)
    {
        $env = app()->environment();
        $envKey = "{$key}_{$env}";
        
        // Try environment-specific setting first
        $value = self::get($group, $envKey, null);
        
        if ($value !== null) {
            return $value;
        }

        // Fallback to generic setting
        return self::get($group, $key, $default);
    }

    /**
     * Settings with user preferences override
     */
    public static function getWithUserPreference(string $group, string $key, string $userKey, $default = null, ?int $userId = null)
    {
        // Try user preference first
        $userValue = self::userGet($userId, $userKey, null, null);
        
        if ($userValue !== null) {
            return $userValue;
        }

        // Fallback to global setting
        return self::get($group, $key, $default);
    }

    /**
     * Paginated settings for different contexts
     */
    public static function getPaginationSize(string $context = 'global', ?int $userId = null): int
    {
        // User preference first
        if ($userId) {
            $userPref = self::userGet($userId, "{$context}_page_size", null, null);
            if ($userPref !== null) {
                return $userPref;
            }
        }

        // Context-specific setting
        $contextSize = match($context) {
            'items' => self::itemsPagination(),
            'suppliers' => self::suppliersPagination(),
            default => self::globalPagination(),
        };

        return $contextSize;
    }
}