<?php

namespace App\Services\Currency;

use App\Models\TenantFeature;
use App\Models\Setting;

class CurrencyModeService
{
    const MODE_SINGLE = 'single';
    const MODE_MULTI  = 'multi';

    /**
     * Get the tenant's current currency configuration.
     * This is read-only — mode cannot be changed after initial setup.
     */
    public static function getSettings(): array
    {
        return [
            'local_currency'       => CurrencyService::getLocalCurrencyCode(),
            'local_currency_id'    => CurrencyService::getLocalCurrencyId(),
            'system_currency_mode' => TenantFeature::isEnabled('multi_currency') ? self::MODE_MULTI : self::MODE_SINGLE,
        ];
    }
}
