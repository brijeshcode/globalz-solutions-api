<?php

namespace App\Helpers;

use App\Models\Accounts\Account;
use App\Models\Landlord\TenantFeature;

class FeatureHelper {

    /**
     * In-memory cache for the current request — avoids repeated cache/DB lookups.
     * Null means not yet loaded; array means already loaded (even if empty).
     */
    private static ?array $features = null;

    /**
     * Return all enabled features as a key => bool map.
     * Loaded once per request and held in memory.
     */
    public static function getAllFeatures(): array
    {
        if (self::$features === null) {
            self::$features = TenantFeature::getForCurrentTenant();
        }

        return self::$features;
    }

    /**
     * Check if a feature is enabled.
     */
    public static function isEnabled(string $key): bool
    {
        return self::getAllFeatures()[$key] ?? false;
    }

    /**
     * Flush the in-memory cache (useful in tests or after feature updates).
     */
    public static function flush(): void
    {
        self::$features = null;
    }

    // ─── Convenience methods ──────────────────────────────────────────────────

    public static function isMultiCurrency(): bool
    {
        return self::isEnabled('multi_currency');
    }

    public static function isExportCustomers(): bool
    {
        return self::isEnabled('export_customers');
    }
}
