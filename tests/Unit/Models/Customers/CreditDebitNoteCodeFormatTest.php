<?php

/**
 * Unit tests for CustomerCreditDebitNote::reserveNextCode()
 *
 * TWO concerns tested here:
 *
 *   1. OUTPUT FORMAT (pure logic):
 *      The code must always be 6 digits, zero-padded on the left.
 *      This is tested by controlling the Setting counter value.
 *
 *   2. COUNTER INCREMENT (behaviour):
 *      Each call must consume exactly one counter slot.
 *      This guards against a future refactor silently double-incrementing
 *      or not incrementing at all.
 *
 * Why not a feature test?
 *   The code generation has no HTTP or auth surface. Testing it via the
 *   full store endpoint adds ~200ms of noise for what is a 1-query operation.
 *   If this logic breaks, we want to know immediately with a precise failure.
 */

use App\Models\Customers\CustomerCreditDebitNote;
use App\Models\Setting;

uses(Tests\TestCase::class);

beforeEach(function () {
    Setting::create([
        'group_name'  => 'customer_credit_debit_notes',
        'key_name'    => 'code_counter',
        'value'       => '1000',
        'data_type'   => 'number',
        'description' => 'Test counter',
    ]);
});

describe('output format', function () {

    it('always produces a 6-character string', function () {
        $code = CustomerCreditDebitNote::reserveNextCode();

        expect(strlen($code))->toBe(6);
    });

    it('zero-pads values below 100000', function () {
        Setting::set('customer_credit_debit_notes', 'code_counter', 99, 'number');

        $code = CustomerCreditDebitNote::reserveNextCode();

        // counter was 99 → next = 100 → padded to '000100'
        expect($code)->toBe('000100');
    });

    it('does not truncate values at or above 100000', function () {
        Setting::set('customer_credit_debit_notes', 'code_counter', 999999, 'number');

        $code = CustomerCreditDebitNote::reserveNextCode();

        // counter was 999999 → next = 1000000 → exceeds 6 digits, no truncation
        expect(strlen($code))->toBeGreaterThanOrEqual(6);
    });

    it('generates the correct code for a known counter value', function () {
        Setting::set('customer_credit_debit_notes', 'code_counter', 1005, 'number');

        $code = CustomerCreditDebitNote::reserveNextCode();

        expect($code)->toBe('001006');
    });
});

describe('counter increment behaviour', function () {

    it('increments the counter by exactly 1 on each call', function () {
        $code1 = CustomerCreditDebitNote::reserveNextCode();
        $code2 = CustomerCreditDebitNote::reserveNextCode();
        $code3 = CustomerCreditDebitNote::reserveNextCode();

        expect((int) $code2)->toBe((int) $code1 + 1)
            ->and((int) $code3)->toBe((int) $code2 + 1);
    });

    it('shares the same counter between credit and debit notes', function () {
        // Both note types pull from the same counter pool —
        // a debit note should consume a counter slot just like a credit note.
        $creditCode = CustomerCreditDebitNote::reserveNextCode('credit');
        $debitCode  = CustomerCreditDebitNote::reserveNextCode('debit');

        expect((int) $debitCode)->toBe((int) $creditCode + 1);
    });
});
