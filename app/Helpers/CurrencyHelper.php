<?php

namespace App\Helpers;

use App\Models\Landlord\TenantFeature;
use App\Models\Setups\Generals\Currencies\Currency;
use Illuminate\Support\Facades\Log;

class CurrencyHelper {

    private static ?array $currencies = null;

    /**
     * Load all currencies with active rates once per request
     */
    private static function getCurrency(int $currencyId): ?Currency
    {
        if (self::$currencies === null) {
            self::$currencies = Currency::with('activeRate')
                ->get()
                ->keyBy('id')
                ->all();
        }

        return self::$currencies[$currencyId] ?? null;
    }

    /**
     * Reset the static currency cache.
     * Called by ResetCurrencyStaticsTask when switching tenants in queue workers.
     */
    public static function resetStaticCache(): void
    {
        self::$currencies = null;
    }

    /**
     * Convert an amount to its USD equivalent.
     *
     * NOTE: In single-currency mode (multi_currency feature OFF), all amounts
     * are treated as 1:1 with USD and returned as-is. This is intentional —
     * do NOT add per-call overrides; the mode is enforced centrally here.
     */
    public static function toUsd( int $currencyId, float $amount, ?float $rate = null): float
    {
        if (!TenantFeature::isEnabled('multi_currency')) {
            return $amount;
        }

        $currency = self::getCurrency($currencyId);

        // If currency not found, return amount as-is (assume it's already in USD)
        if (!$currency) {
            Log::warning("Currency with ID {$currencyId} not found. Returning amount as-is.");
            return $amount;
        }

        // Get rate from activeRate relationship if not provided
        if(is_null($rate)){
            if (!$currency->activeRate) {
                // Log::warning("No active rate found for currency {$currency->code}. Returning amount as-is.");
                return $amount;
            }
            $rate = $currency->activeRate->rate;
        }

        // Prevent division by zero
        if ($rate == 0) {
            Log::error("Currency rate is zero for currency {$currency->code}. Returning amount as-is.");
            return $amount;
        }

        // Convert based on calculation type
        if($currency->calculation_type == 'multiply'){
            return  $amount * $rate;
        }else{
            return $amount / $rate ;
        }
    }

    /**
     * Convert a USD amount back to the given currency.
     *
     * NOTE: In single-currency mode (multi_currency feature OFF), returns the
     * amount as-is (1:1 with USD). Enforced centrally — see toUsd().
     */
    public static function fromUsd(int $currencyId, float $amountUsd, ?float $rate = null): float
    {
        if (!TenantFeature::isEnabled('multi_currency')) {
            return $amountUsd;
        }

        $currency = self::getCurrency($currencyId);

        // If currency not found, return amount as-is (assume it stays in USD)
        if (!$currency) {
            Log::warning("Currency with ID {$currencyId} not found. Returning amount as-is.");
            return $amountUsd;
        }

        // Get rate from activeRate relationship if not provided
        if(is_null($rate)){
            if (!$currency->activeRate) {
                // Log::warning("No active rate found for currency {$currency->code}. Returning amount as-is.");
                return $amountUsd;
            }
            $rate = $currency->activeRate->rate;
        }

        // Prevent division by zero
        if ($rate == 0) {
            Log::error("Currency rate is zero for currency {$currency->code}. Returning amount as-is.");
            return $amountUsd;
        }

        // Convert based on calculation type (reverse of toUsd)
        if($currency->calculation_type == 'multiply'){
            return $amountUsd / $rate;
        }else{
            return $amountUsd * $rate;
        }
    }

}
