<?php 

namespace App\Helpers;

use App\Models\Accounts\Account;
use App\Models\Setups\Generals\Currencies\Currency;
use Illuminate\Support\Facades\Log;

class CurrencyHelper {
    
    public static function toUsd( int $currencyId, float $amount, ?float $rate = null): float
    {
        $currency = Currency::with('activeRate')->find($currencyId);

        // If currency not found, return amount as-is (assume it's already in USD)
        if (!$currency) {
            Log::warning("Currency with ID {$currencyId} not found. Returning amount as-is.");
            return $amount;
        }

        // Get rate from activeRate relationship if not provided
        if(is_null($rate)){
            if (!$currency->activeRate) {
                Log::warning("No active rate found for currency {$currency->code}. Returning amount as-is.");
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

}