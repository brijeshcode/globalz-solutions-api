<?php

namespace App\Helpers;

use App\Models\Accounts\Account;
use Illuminate\Support\Facades\Config;

class FeatureHelper {

    /**
     * In-memory cache for the current request — avoids repeated cache/DB lookups.
     * Null means not yet loaded; array means already loaded (even if empty).
     */
    /**
     * Return all enabled features as a key => bool map.
     */
    public static function getAllFeatures(): array
    {
        return array_filter(Config::get('features', []), fn($v) => $v === true);
    }

    /**
     * Check if a feature is enabled.
     */
    public static function isEnabled(string $key): bool
    {
        return Config::get("features.{$key}", false);
    }

    // ─── Convenience methods ──────────────────────────────────────────────────

    public static function isMultiCurrency(): bool
    {
        return self::isEnabled('multi_currency');
    }
}
