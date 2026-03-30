<?php

/**
 * Unit tests for CurrencyHelper::toUsd()
 *
 * WHY these are not feature tests:
 *   The conversion formula (multiply vs divide) is the core of the amount_usd
 *   validation. If this logic is wrong, every financial record in the system
 *   is wrong. It deserves its own focused test that does not go through HTTP.
 *
 * What we need from the DB:
 *   Only a Currency row (for calculation_type). No HTTP, no controllers,
 *   no middleware. Each test runs in ~5ms instead of ~200ms.
 */

use App\Helpers\CurrencyHelper;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Landlord\TenantFeature;

uses(Tests\TestCase::class);

beforeEach(function () {
    CurrencyHelper::resetStaticCache();
});

describe('multiply calculation type', function () {

    it('converts amount to USD by multiplying by the rate', function () {
        $currency = Currency::factory()->eur()->create([
            'is_active'        => true,
            'calculation_type' => 'multiply',
        ]);

        // EUR with rate 1.25 → 200 × 1.25 = 250
        $result = CurrencyHelper::toUsd($currency->id, 200.00, 1.25);

        expect($result)->toBe(250.0);
    });

    it('returns the original amount when rate is 1', function () {
        $currency = Currency::factory()->eur()->create([
            'is_active'        => true,
            'calculation_type' => 'multiply',
        ]);

        $result = CurrencyHelper::toUsd($currency->id, 150.00, 1.0);

        expect($result)->toBe(150.0);
    });
});

describe('divide calculation type', function () {

    it('converts amount to USD by dividing by the rate', function () {
        // Example: currency where 1 USD = 0.8 units → amount / 0.8 = USD
        $currency = Currency::factory()->create([
            'is_active'        => true,
            'calculation_type' => 'divide',
        ]);

        // 200 ÷ 0.8 = 250
        $result = CurrencyHelper::toUsd($currency->id, 200.00, 0.8);

        expect($result)->toBe(250.0);
    });
});

describe('edge cases', function () {

    it('returns the original amount when rate is zero (guards division by zero)', function () {
        $currency = Currency::factory()->eur()->create([
            'is_active'        => true,
            'calculation_type' => 'multiply',
        ]);

        $result = CurrencyHelper::toUsd($currency->id, 100.00, 0);

        // Should return amount as-is rather than throw
        expect($result)->toBe(100.0);
    });

    it('returns the original amount when the currency id does not exist', function () {
        $result = CurrencyHelper::toUsd(999999, 100.00, 1.25);

        expect($result)->toBe(100.0);
    });

    it('returns the original amount when multi_currency feature is disabled', function () {
        // Temporarily disable the feature
        TenantFeature::where('feature_id', function ($q) {
            $q->select('id')->from('features')->where('key', 'multi_currency');
        })->update(['is_enabled' => false]);

        TenantFeature::clearCache($this->tenant->id);

        $currency = Currency::factory()->eur()->create([
            'is_active'        => true,
            'calculation_type' => 'multiply',
        ]);

        // With multi_currency OFF, toUsd() returns amount as-is (1:1 with USD)
        $result = CurrencyHelper::toUsd($currency->id, 200.00, 1.25);

        expect($result)->toBe(200.0);
    });
});
