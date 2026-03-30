<?php

use App\Models\Accounts\IncomeTransaction;
use App\Models\Setting;
use Tests\Feature\Accounts\IncomeTransactions\Concerns\HasIncomeTransactionSetup;

uses(HasIncomeTransactionSetup::class);

beforeEach(function () {
    $this->setUpIncomeTransactions();
});

it('auto-generates code starting from counter value', function () {
    $this->postJson(route('income-transactions.store'), $this->transactionPayload(['amount' => 750.00]))->assertCreated();

    $transaction = IncomeTransaction::where('amount', 750.00)->first();

    expect($transaction->code)->not()->toBeNull()
        ->and((int) ltrim($transaction->code, IncomeTransaction::PREFIX))->toBeGreaterThanOrEqual(100);
});

it('ignores provided code and always generates a new one', function () {
    $this->postJson(route('income-transactions.store'), $this->transactionPayload(['amount' => 999.00, 'code' => '99999']))->assertCreated();

    $transaction = IncomeTransaction::where('amount', 999.00)->first();

    expect($transaction->code)->not()->toBe('99999')
        ->and((int) ltrim($transaction->code, IncomeTransaction::PREFIX))->toBeGreaterThanOrEqual(100);
});

it('returns next suggested code from current setting', function () {
    Setting::set('income_transactions', 'code_counter', 101, 'number');

    expect((int) IncomeTransaction::getNextSuggestedCode())->toBe(101);
});

it('uses counter value and increments after creation', function () {
    Setting::set('income_transactions', 'code_counter', 105, 'number');

    $response = $this->postJson(route('income-transactions.store'), $this->transactionPayload())->assertCreated();

    $code = (int) ltrim($response->json('data.code'), IncomeTransaction::PREFIX);
    expect($code)->toBe(105);
    expect((int) IncomeTransaction::getNextSuggestedCode())->toBe(106);
});

it('auto-creates code counter setting when missing', function () {
    Setting::where('group_name', 'income_transactions')->where('key_name', 'code_counter')->delete();
    Setting::clearCache();

    $response = $this->postJson(route('income-transactions.store'), $this->transactionPayload())->assertCreated();

    $firstCode = (int) ltrim($response->json('data.code'), IncomeTransaction::PREFIX);
    expect($firstCode)->toBe(100);

    $setting = Setting::where('group_name', 'income_transactions')->where('key_name', 'code_counter')->first();
    expect($setting)->not()->toBeNull()
        ->and($setting->data_type)->toBe('number')
        ->and((int) $setting->value)->toBe(101);
});

it('generates sequential codes for consecutive transactions', function () {
    IncomeTransaction::withTrashed()->forceDelete();

    $response1 = $this->postJson(route('income-transactions.store'), $this->transactionPayload(['amount' => 1000.00]))->assertCreated();
    $response2 = $this->postJson(route('income-transactions.store'), $this->transactionPayload(['amount' => 2000.00]))->assertCreated();

    $code1 = (int) ltrim($response1->json('data.code'), IncomeTransaction::PREFIX);
    $code2 = (int) ltrim($response2->json('data.code'), IncomeTransaction::PREFIX);

    expect($code1)->toBeGreaterThanOrEqual(100)
        ->and($code2)->toBe($code1 + 1);
});

it('generates strictly increasing codes for concurrent transactions', function () {
    $transactions = [];
    for ($i = 0; $i < 5; $i++) {
        $transactions[] = $this->createTransaction();
    }

    $codes = collect($transactions)
        ->map(fn ($t) => (int) ltrim($t->code, IncomeTransaction::PREFIX))
        ->sort()
        ->values();

    for ($i = 1; $i < count($codes); $i++) {
        expect($codes[$i])->toBeGreaterThan($codes[$i - 1]);
    }
});
