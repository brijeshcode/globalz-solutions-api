<?php

namespace App\Services\Currency;

use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\Generals\Currencies\currencyRate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class CurrencyService
{
    const CACHE_KEY_PREFIX = 'currency_rate_';
    const CACHE_DURATION = 3600; // 1 hour
    const BASE_CURRENCY_CACHE_KEY = 'base_currency_id';
    const DEFAULT_BASE_CURRENCY_CODE = 'USD';

    /**
     * Core conversion method - handles all currency conversions
     * Rate convention: 1 foreign currency = X base currency units
     */
    public static function convert(float $amount, int $fromCurrencyId, int $toCurrencyId, ?float $fromRate = null, ?float $toRate = null): float
    {
        if ($fromCurrencyId === $toCurrencyId) {
            return $amount;
        }

        // Convert to base currency first
        $baseAmount = self::convertToBase($amount, $fromCurrencyId, $fromRate);
        
        // Convert from base to target currency
        return self::convertFromBase($baseAmount, $toCurrencyId, $toRate);
    }

    /**
     * Convert amount to base currency
     */
    public static function convertToBase(float $amount, int $fromCurrencyId, ?float $customRate = null): float
    {
        if (self::isBaseCurrency($fromCurrencyId)) {
            return $amount;
        }

        $rate = $customRate ?? self::getRate($fromCurrencyId);
        
        if ($rate === null) {
            throw new InvalidArgumentException("Currency rate not found for currency ID: {$fromCurrencyId}");
        }

        return $amount * $rate;
    }

    /**
     * Convert amount from base currency
     */
    public static function convertFromBase(float $baseAmount, int $toCurrencyId, ?float $customRate = null): float
    {
        if (self::isBaseCurrency($toCurrencyId)) {
            return $baseAmount;
        }

        $rate = $customRate ?? self::getRate($toCurrencyId);
        
        if ($rate === null) {
            throw new InvalidArgumentException("Currency rate not found for currency ID: {$toCurrencyId}");
        }

        return $baseAmount / $rate;
    }

    /**
     * Legacy methods for backward compatibility
     */
    public static function convertToBaseWithRate(float $amount, int $fromCurrencyId, float $customRate): float
    {
        return self::convertToBase($amount, $fromCurrencyId, $customRate);
    }

    public static function convertFromBaseWithRate(float $baseAmount, int $toCurrencyId, float $customRate): float
    {
        return self::convertFromBase($baseAmount, $toCurrencyId, $customRate);
    }

    public static function convertWithRates(float $amount, int $fromCurrencyId, int $toCurrencyId, float $fromRate, float $toRate): float
    {
        return self::convert($amount, $fromCurrencyId, $toCurrencyId, $fromRate, $toRate);
    }

    public static function convertToUSD(float $amount, int $fromCurrencyId): float
    {
        $usdId = self::getUSDCurrencyId();
        return $usdId ? self::convert($amount, $fromCurrencyId, $usdId) : $amount;
    }

    public static function convertFromUSD(float $amountUSD, int $toCurrencyId): float
    {
        $usdId = self::getUSDCurrencyId();
        return $usdId ? self::convert($amountUSD, $usdId, $toCurrencyId) : $amountUSD;
    }

    /**
     * Check if currency is the current base currency
     */
    public static function isBaseCurrency(int $currencyId): bool
    {
        return $currencyId === self::getBaseCurrencyId();
    }

    /**
     * Check if currency is USD (for legacy compatibility)
     */
    public static function isUSD(int $currencyId): bool
    {
        $currency = self::getCurrency($currencyId);
        return $currency && strtoupper($currency->code) === 'USD';
    }

    /**
     * Get current base currency ID
     */
    public static function getBaseCurrencyId(): ?int
    {
        return Cache::remember(self::BASE_CURRENCY_CACHE_KEY, self::CACHE_DURATION, function () {
            // First try to get from settings/config
            $baseCurrencyCode = config('app.base_currency', self::DEFAULT_BASE_CURRENCY_CODE);
            $baseCurrency = Currency::where('code', $baseCurrencyCode)->first();
            
            if ($baseCurrency) {
                return $baseCurrency->id;
            }

            // Fallback to USD if configured currency not found
            $usdCurrency = Currency::where('code', 'USD')->first();
            return $usdCurrency?->id;
        });
    }

    /**
     * Set base currency
     */
    public static function setBaseCurrency(int $currencyId): bool
    {
        $currency = self::getCurrency($currencyId);
        
        if (!$currency || !$currency->isActive()) {
            throw new InvalidArgumentException("Currency not found or inactive");
        }

        // Clear cache
        Cache::forget(self::BASE_CURRENCY_CACHE_KEY);
        
        // You might want to store this in a settings table or config
        // For now, we'll just update the cache with the new base currency
        Cache::put(self::BASE_CURRENCY_CACHE_KEY, $currencyId, self::CACHE_DURATION);
        
        return true;
    }

    /**
     * Get base currency model
     */
    public static function getBaseCurrency(): ?Currency
    {
        $baseCurrencyId = self::getBaseCurrencyId();
        return $baseCurrencyId ? self::getCurrency($baseCurrencyId) : null;
    }

    /**
     * Get USD currency ID (for legacy compatibility)
     */
    public static function getUSDCurrencyId(): ?int
    {
        static $usdId = null;
        
        if ($usdId === null) {
            $usdCurrency = Currency::where('code', 'USD')->first();
            $usdId = $usdCurrency?->id;
        }
        
        return $usdId;
    }

    /**
     * Get current exchange rate for a currency (base currency always returns 1.0)
     */
    public static function getRate(int $currencyId): ?float
    {
        // Base currency always has rate 1.0
        if (self::isBaseCurrency($currencyId)) {
            return 1.0;
        }

        $cacheKey = self::CACHE_KEY_PREFIX . $currencyId;
        
        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($currencyId) {
            $currency = Currency::with('activeRate')->find($currencyId);
            return $currency?->getCurrentRate();
        });
    }

    /**
     * Format amount with currency symbol and formatting rules
     */
    public static function format(float $amount, int $currencyId): string
    {
        $currency = self::getCurrency($currencyId);
        
        if (!$currency) {
            return number_format($amount, 2);
        }

        return $currency->formatAmount($amount);
    }

    /**
     * Format amount with custom currency
     */
    public static function formatWithCurrency(float $amount, Currency $currency): string
    {
        return $currency->formatAmount($amount);
    }

    /**
     * Get currency model with caching
     */
    public static function getCurrency(int $currencyId): ?Currency
    {
        $cacheKey = 'currency_' . $currencyId;
        
        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($currencyId) {
            return Currency::find($currencyId);
        });
    }

    /**
     * Update exchange rate for a currency
     */
    public static function updateRate(int $currencyId, float $rate, ?string $note = null): currencyRate
    {
        return DB::transaction(function () use ($currencyId, $rate, $note) {
            // Deactivate current rate
            currencyRate::where('currency_id', $currencyId)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            // Create new active rate
            $newRate = currencyRate::create([
                'currency_id' => $currencyId,
                'rate' => $rate,
                'is_active' => true,
                'note' => $note
            ]);

            // Clear cache for this currency
            self::clearCurrencyCache($currencyId);

            return $newRate;
        });
    }

    /**
     * Bulk update multiple currency rates
     */
    public static function bulkUpdateRates(array $rates, ?string $note = null): array
    {
        return DB::transaction(function () use ($rates, $note) {
            $results = [];

            foreach ($rates as $currencyId => $rate) {
                try {
                    $results[$currencyId] = self::updateRate($currencyId, $rate, $note);
                } catch (\Exception $e) {
                    $results[$currencyId] = [
                        'error' => $e->getMessage(),
                        'currency_id' => $currencyId,
                        'rate' => $rate
                    ];
                }
            }

            return $results;
        });
    }

    /**
     * Get all active currencies with their current rates
     */
    public static function getAllActiveCurrencies(): \Illuminate\Database\Eloquent\Collection
    {
        return Currency::with('activeRate')
            ->active()
            ->orderBy('name')
            ->get();
    }

    /**
     * Get exchange rate history for a currency
     */
    public static function getRateHistory(int $currencyId, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return currencyRate::where('currency_id', $currencyId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Calculate conversion with formatting
     */
    public static function convertAndFormat(float $amount, int $fromCurrencyId, int $toCurrencyId): array
    {
        $convertedAmount = self::convert($amount, $fromCurrencyId, $toCurrencyId);
        
        return [
            'original_amount' => $amount,
            'converted_amount' => $convertedAmount,
            'original_formatted' => self::format($amount, $fromCurrencyId),
            'converted_formatted' => self::format($convertedAmount, $toCurrencyId),
            'from_rate' => self::getRate($fromCurrencyId),
            'to_rate' => self::getRate($toCurrencyId)
        ];
    }

    /**
     * Get conversion rates between multiple currencies
     */
    public static function getConversionMatrix(array $currencyIds): array
    {
        $matrix = [];
        
        foreach ($currencyIds as $fromId) {
            foreach ($currencyIds as $toId) {
                if ($fromId === $toId) {
                    $matrix[$fromId][$toId] = 1.0;
                } else {
                    try {
                        $fromRate = self::getRate($fromId);
                        $toRate = self::getRate($toId);
                        $matrix[$fromId][$toId] = $toRate / $fromRate;
                    } catch (\Exception $e) {
                        $matrix[$fromId][$toId] = null;
                    }
                }
            }
        }
        
        return $matrix;
    }

    /**
     * Validate currency and rate
     */
    public static function validateCurrency(int $currencyId): bool
    {
        $currency = self::getCurrency($currencyId);
        return $currency && $currency->isActive() && self::getRate($currencyId) !== null;
    }

    /**
     * Get default currency (current base currency)
     */
    public static function getDefaultCurrency(): ?Currency
    {
        return self::getBaseCurrency();
    }

    /**
     * Clear currency cache
     */
    public static function clearCurrencyCache(int $currencyId): void
    {
        Cache::forget(self::CACHE_KEY_PREFIX . $currencyId);
        Cache::forget('currency_' . $currencyId);
    }

    /**
     * Clear all currency caches including base currency
     */
    public static function clearAllCurrencyCache(): void
    {
        $currencies = Currency::pluck('id');
        
        foreach ($currencies as $currencyId) {
            self::clearCurrencyCache($currencyId);
        }
        
        // Clear base currency cache
        Cache::forget(self::BASE_CURRENCY_CACHE_KEY);
    }

    /**
     * Get all available base currency options
     */
    public static function getAvailableBaseCurrencies(): \Illuminate\Database\Eloquent\Collection
    {
        return Currency::active()
            ->whereHas('activeRate')
            ->orderBy('name')
            ->get();
    }

    /**
     * Switch base currency (for admin operations)
     */
    public static function switchBaseCurrency(int $newBaseCurrencyId, ?string $reason = null): array
    {
        $oldBaseCurrency = self::getBaseCurrency();
        $newBaseCurrency = self::getCurrency($newBaseCurrencyId);
        
        if (!$newBaseCurrency || !$newBaseCurrency->isActive()) {
            throw new InvalidArgumentException("New base currency not found or inactive");
        }

        return DB::transaction(function () use ($oldBaseCurrency, $newBaseCurrency, $reason) {
            // Store old base currency info
            $oldInfo = [
                'id' => $oldBaseCurrency?->id,
                'code' => $oldBaseCurrency?->code,
                'name' => $oldBaseCurrency?->name
            ];

            // Set new base currency
            self::setBaseCurrency($newBaseCurrency->id);
            
            // Clear all caches to ensure fresh rates
            self::clearAllCurrencyCache();
            
            return [
                'old_base_currency' => $oldInfo,
                'new_base_currency' => [
                    'id' => $newBaseCurrency->id,
                    'code' => $newBaseCurrency->code,
                    'name' => $newBaseCurrency->name
                ],
                'reason' => $reason,
                'switched_at' => now(),
                'warning' => 'All existing rates are now relative to ' . $newBaseCurrency->code
            ];
        });
    }

    /**
     * Parse amount from formatted string
     */
    public static function parseAmount(string $formattedAmount, int $currencyId): float
    {
        $currency = self::getCurrency($currencyId);
        
        if (!$currency) {
            // Fallback parsing
            return (float) preg_replace('/[^\d.]/', '', $formattedAmount);
        }

        // Remove currency symbol
        $amount = str_replace($currency->symbol, '', $formattedAmount);
        
        // Replace thousand separator with empty string
        $amount = str_replace($currency->thousand_separator, '', $amount);
        
        // Replace decimal separator with dot
        if ($currency->decimal_separator !== '.') {
            $amount = str_replace($currency->decimal_separator, '.', $amount);
        }
        
        return (float) trim($amount);
    }

    /**
     * Compare amounts in different currencies
     */
    public static function compareAmounts(float $amount1, int $currency1Id, float $amount2, int $currency2Id): array
    {
        $amount1Base = self::convertToBase($amount1, $currency1Id);
        $amount2Base = self::convertToBase($amount2, $currency2Id);
        $difference = $amount1Base - $amount2Base;
        
        return [
            'amount1_base' => $amount1Base,
            'amount2_base' => $amount2Base,
            'difference_base' => $difference,
            'amount1_formatted' => self::format($amount1, $currency1Id),
            'amount2_formatted' => self::format($amount2, $currency2Id),
            'comparison' => $difference > 0 ? 'greater' : ($difference < 0 ? 'less' : 'equal')
        ];
    }

    /**
     * Get currency rate with fallback
     */
    public static function getRateWithFallback(int $currencyId, float $fallbackRate = 1.0): float
    {
        $rate = self::getRate($currencyId);
        return $rate ?? $fallbackRate;
    }

    /**
     * Format multiple amounts with their currencies
     */
    public static function formatMultiple(array $amounts): array
    {
        $formatted = [];
        
        foreach ($amounts as $currencyId => $amount) {
            $formatted[$currencyId] = [
                'amount' => $amount,
                'formatted' => self::format($amount, $currencyId),
                'currency' => self::getCurrency($currencyId)
            ];
        }
        
        return $formatted;
    }
}