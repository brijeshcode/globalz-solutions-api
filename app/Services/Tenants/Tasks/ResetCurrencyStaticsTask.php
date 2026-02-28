<?php

namespace App\Services\Tenants\Tasks;

use App\Helpers\CurrencyHelper;
use App\Services\Currency\CurrencyService;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Tasks\SwitchTenantTask;

class ResetCurrencyStaticsTask implements SwitchTenantTask
{
    /**
     * Called when a tenant is made current.
     * Resets all PHP static caches that are NOT covered by PrefixCacheTask.
     * Required for queue workers that process multiple tenants in sequence.
     */
    public function makeCurrent(IsTenant $tenant): void
    {
        CurrencyHelper::resetStaticCache();
        CurrencyService::resetStaticCache();
    }

    /**
     * Called when no tenant is active (e.g. landlord context).
     */
    public function forgetCurrent(): void
    {
        CurrencyHelper::resetStaticCache();
        CurrencyService::resetStaticCache();
    }
}
