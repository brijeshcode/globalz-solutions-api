<?php

/**
 * Tests for the local-currency equivalents added to the VAT report.
 *
 * The report stores/sums everything in USD. Each metric now also gets a
 * `*_local` sibling converted to the tenant's configured local currency
 * (Setting currency.local_currency), plus a `local_currency` metadata block.
 *
 * Called directly (no HTTP) — we only need the tenant DB, a currency + rate,
 * and one approved TAX sale. The conversion formula itself is covered by
 * CurrencyHelperTest; here we verify the controller wires it up correctly.
 */

use App\Helpers\CurrencyHelper;
use App\Http\Controllers\Api\Reports\Finance\VatReportController;
use App\Models\Customers\Sale;
use App\Models\Landlord\TenantFeature;
use App\Models\Setting;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\Generals\Currencies\currencyRate;
use App\Services\Currency\CurrencyService;
use Illuminate\Support\Facades\Cache;

/**
 * Configure a local currency (code, calculation_type) with an active rate and
 * point the tenant setting at it, clearing all the relevant caches.
 */
function configureLocalCurrency(string $code, string $calculationType, float $rate): Currency
{
    $currency = Currency::factory()->create([
        'code'             => $code,
        'is_active'        => true,
        'calculation_type' => $calculationType,
    ]);

    currencyRate::create([
        'currency_id' => $currency->id,
        'rate'        => $rate,
        'is_active'   => true,
    ]);

    Setting::set('currency', 'local_currency', $code);

    Cache::flush();
    CurrencyService::resetStaticCache();
    CurrencyHelper::resetStaticCache();

    return $currency;
}

beforeEach(function () {
    CurrencyHelper::resetStaticCache();
    CurrencyService::resetStaticCache();
});

it('adds a local-currency sibling to every USD metric', function () {
    // LBP-style: to convert LOCAL -> USD you divide, so USD -> LOCAL multiplies.
    configureLocalCurrency('LBP', 'divide', 3.0);

    Sale::factory()->taxSale()->create([
        'approved_by'          => \App\Models\User::factory(),
        'total_usd'            => 1000.00,
        'total_tax_amount_usd' => 150.00,
    ]);

    $report = (new VatReportController())->calculateVatReport(null, null);

    // USD figures unchanged
    expect($report['total_sales'])->toBe(1000.0)
        ->and($report['vat_sales_total'])->toBe(150.0);

    // Local siblings = USD * rate (divide currency → fromUsd multiplies)
    expect($report['total_sales_local'])->toBe(3000.0)
        ->and($report['vat_sales_total_local'])->toBe(450.0);

    // Every USD metric has a _local sibling
    foreach (['total_sales', 'total_returns', 'net_sales', 'vat_sales_total',
        'vat_return_total', 'net_vat_sales', 'vat_expense_total',
        'expense_vat_total', 'vat_purchase_total', 'vat_difference'] as $key) {
        expect($report)->toHaveKey($key . '_local');
    }

    // Metadata block identifies what "local" means
    expect($report['local_currency'])->toMatchArray(['code' => 'LBP']);
});

it('mirrors USD values into local when multi_currency is disabled', function () {
    configureLocalCurrency('LBP', 'divide', 3.0);

    TenantFeature::where('feature_id', function ($q) {
        $q->select('id')->from('features')->where('key', 'multi_currency');
    })->update(['is_enabled' => false]);
    TenantFeature::clearCache($this->tenant->id);

    Sale::factory()->taxSale()->create([
        'approved_by'          => \App\Models\User::factory(),
        'total_usd'            => 1000.00,
        'total_tax_amount_usd' => 150.00,
    ]);

    $report = (new VatReportController())->calculateVatReport(null, null);

    // Single-currency mode: local == USD (no conversion)
    expect($report['total_sales_local'])->toBe($report['total_sales'])
        ->and($report['vat_sales_total_local'])->toBe($report['vat_sales_total']);
});
