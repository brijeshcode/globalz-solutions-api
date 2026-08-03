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

    /**
     * No-op: features are read directly from config here (no per-request cache to
     * clear). Kept for API-compatibility with callers that flush the feature cache.
     */
    public static function flush(): void
    {
        // Config-based resolution holds no state, so there is nothing to flush.
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

    public static function isDatabaseMirror(): bool
    {
        return self::isEnabled('database_mirror');
    }

    public static function isVehicleModule(): bool
    {
        return self::isEnabled('vehicle_module');
    }

    public static function isBugLock(): bool
    {
        return self::isEnabled('bug_lock');
    }

    public static function expensePaymentEnabled(): bool
    {
        return self::isEnabled('expense_deferred_payment');
    }

    public static function isSaleProfitRecalculation(): bool
    {
        return self::isEnabled('sale_profit_recalculation');
    }

    public static function isProformaInvoice(): bool
    {
        return self::isEnabled('proforma_invoice');
    }
}
