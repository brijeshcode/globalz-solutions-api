<?php

/**
 * Unit tests for CustomerCreditDebitNotesStoreRequest::withValidator()
 *
 * PURE UNIT (no DB, no HTTP):
 *   The prefix/type mismatch rules are a plain PHP lookup table — no external
 *   dependencies, so we test them by creating the FormRequest directly and
 *   calling withValidator() ourselves. These run in ~1ms.
 *
 * NEAR-UNIT (minimal DB, no HTTP):
 *   The amount_usd and inactive-customer rules call the DB inside withValidator(),
 *   so they need TestCase + RefreshDatabase. They still skip the full HTTP stack,
 *   making them ~5× faster than the equivalent feature test.
 */

use App\Http\Requests\Api\Customers\CustomerCreditDebitNotesStoreRequest;
use App\Models\Customers\Customer;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setting;
use Illuminate\Support\Facades\Validator;

// ─────────────────────────────────────────────────────────────
// PURE UNIT — no DB, no TestCase required
// ─────────────────────────────────────────────────────────────

describe('prefix / type rule (pure unit)', function () {

    // Helper: build a request with given data and run withValidator()
    function makeStoreRequest(array $data): \Illuminate\Validation\Validator
    {
        $request = new CustomerCreditDebitNotesStoreRequest();
        $request->replace($data);

        $validator = Validator::make($data, []);
        $request->withValidator($validator);

        return $validator;
    }

    it('rejects a debit prefix (DBN) for a credit note', function () {
        $v = makeStoreRequest(['type' => 'credit', 'prefix' => 'DBN']);

        expect($v->errors()->has('prefix'))->toBeTrue();
    });

    it('rejects a debit prefix (DBX) for a credit note', function () {
        $v = makeStoreRequest(['type' => 'credit', 'prefix' => 'DBX']);

        expect($v->errors()->has('prefix'))->toBeTrue();
    });

    it('rejects a credit prefix (CRN) for a debit note', function () {
        $v = makeStoreRequest(['type' => 'debit', 'prefix' => 'CRN']);

        expect($v->errors()->has('prefix'))->toBeTrue();
    });

    it('rejects a credit prefix (CRX) for a debit note', function () {
        $v = makeStoreRequest(['type' => 'debit', 'prefix' => 'CRX']);

        expect($v->errors()->has('prefix'))->toBeTrue();
    });

    it('accepts CRN as a valid prefix for a credit note', function () {
        $v = makeStoreRequest(['type' => 'credit', 'prefix' => 'CRN']);

        expect($v->errors()->has('prefix'))->toBeFalse();
    });

    it('accepts CRX as a valid prefix for a credit note', function () {
        $v = makeStoreRequest(['type' => 'credit', 'prefix' => 'CRX']);

        expect($v->errors()->has('prefix'))->toBeFalse();
    });

    it('accepts DBN as a valid prefix for a debit note', function () {
        $v = makeStoreRequest(['type' => 'debit', 'prefix' => 'DBN']);

        expect($v->errors()->has('prefix'))->toBeFalse();
    });

    it('accepts DBX as a valid prefix for a debit note', function () {
        $v = makeStoreRequest(['type' => 'debit', 'prefix' => 'DBX']);

        expect($v->errors()->has('prefix'))->toBeFalse();
    });

    it('skips the prefix rule when type is missing', function () {
        // withValidator() only runs the check when both fields are present.
        // A missing type is caught by the required rule, not this custom rule.
        $v = makeStoreRequest(['prefix' => 'DBN']);

        expect($v->errors()->has('prefix'))->toBeFalse();
    });
});

// ─────────────────────────────────────────────────────────────
// NEAR-UNIT — minimal DB (Currency + Setting), no HTTP stack
// ─────────────────────────────────────────────────────────────

uses(Tests\TestCase::class);

beforeEach(function () {
    $this->currency = Currency::factory()->eur()->create([
        'is_active'        => true,
        'calculation_type' => 'multiply',
    ]);

    Setting::create([
        'group_name' => 'customer_credit_debit_notes',
        'key_name'   => 'code_counter',
        'value'      => '1000',
        'data_type'  => 'number',
    ]);

    // Clear the static cache so CurrencyHelper sees the new currency
    \App\Helpers\CurrencyHelper::resetStaticCache();
});

describe('amount_usd calculation rule (near-unit)', function () {

    it('rejects amount_usd that does not match amount × rate', function () {
        // EUR multiply: expected = 100 × 1.25 = 125, but we send 200 → invalid
        $v = makeStoreRequest([
            'currency_id'   => $this->currency->id,
            'currency_rate' => 1.25,
            'amount'        => 100.00,
            'amount_usd'    => 200.00,
        ]);

        expect($v->errors()->has('amount_usd'))->toBeTrue();
    });

    it('accepts amount_usd that matches amount × rate exactly', function () {
        // 100 × 1.25 = 125
        $v = makeStoreRequest([
            'currency_id'   => $this->currency->id,
            'currency_rate' => 1.25,
            'amount'        => 100.00,
            'amount_usd'    => 125.00,
        ]);

        expect($v->errors()->has('amount_usd'))->toBeFalse();
    });

    it('accepts amount_usd within the 0.01 tolerance band', function () {
        // 100 × 1.25 = 125, sending 125.009 is within ±0.01
        $v = makeStoreRequest([
            'currency_id'   => $this->currency->id,
            'currency_rate' => 1.25,
            'amount'        => 100.00,
            'amount_usd'    => 125.009,
        ]);

        expect($v->errors()->has('amount_usd'))->toBeFalse();
    });

    it('rejects amount_usd that exceeds the 0.01 tolerance band', function () {
        // 100 × 1.25 = 125, sending 125.02 is outside ±0.01
        $v = makeStoreRequest([
            'currency_id'   => $this->currency->id,
            'currency_rate' => 1.25,
            'amount'        => 100.00,
            'amount_usd'    => 125.02,
        ]);

        expect($v->errors()->has('amount_usd'))->toBeTrue();
    });

    it('skips the check when any of the three values is missing', function () {
        // withValidator() only runs if amount && amount_usd && currency_rate are all present
        $v = makeStoreRequest([
            'currency_id' => $this->currency->id,
            'amount'      => 100.00,
            // amount_usd and currency_rate are missing
        ]);

        expect($v->errors()->has('amount_usd'))->toBeFalse();
    });
});

describe('inactive customer rule (near-unit)', function () {

    it('rejects a customer that is inactive', function () {
        $inactive = Customer::factory()->create(['is_active' => false]);

        $v = makeStoreRequest(['customer_id' => $inactive->id]);

        expect($v->errors()->has('customer_id'))->toBeTrue();
    });

    it('does not flag an active customer', function () {
        $active = Customer::factory()->create(['is_active' => true]);

        $v = makeStoreRequest(['customer_id' => $active->id]);

        expect($v->errors()->has('customer_id'))->toBeFalse();
    });
});
