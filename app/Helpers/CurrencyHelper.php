<?php

namespace App\Helpers;

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

    public static function toUsd( int $currencyId, float $amount, ?float $rate = null): float
    {
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

    public static function fromUsd(int $currencyId, float $amountUsd, ?float $rate = null): float
    {
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
