<?php

use App\Models\Expenses\ExpenseTransaction;
use App\Models\Setting;
use Tests\Feature\Accounts\ExpenseTransactions\Concerns\HasExpenseTransactionSetup;

uses(HasExpenseTransactionSetup::class);

beforeEach(function () {
    $this->setUpExpenseTransactions();
});

// Strip the 'EXP' prefix before numeric comparison
function expCodeInt(string $code): int
{
    return (int) str_replace(ExpenseTransaction::PREFIX, '', $code);
}

it('auto-generates code starting from counter value', function () {
    $this->postJson(route('expense-transactions.store'), $this->transactionPayload(['amount' => 750.00]))->assertCreated();

    $transaction = ExpenseTransaction::where('amount', 750.00)->first();

    expect($transaction->code)->not()->toBeNull()
        ->and(expCodeInt($transaction->code))->toBeGreaterThanOrEqual(100);
});

it('ignores provided code and always generates a new one', function () {
    $this->postJson(route('expense-transactions.store'), $this->transactionPayload(['amount' => 999.00, 'code' => '99999']))->assertCreated();

    $transaction = ExpenseTransaction::where('amount', 999.00)->first();

    expect($transaction->code)->not()->toBe('99999')
        ->and(expCodeInt($transaction->code))->toBeGreaterThanOrEqual(100);
});

it('returns next suggested code from current setting', function () {
    Setting::set('expense_transactions', 'code_counter', 101, 'number');

    expect((int) ExpenseTransaction::getNextSuggestedCode())->toBe(101);
});

it('uses counter value and increments after creation', function () {
    Setting::set('expense_transactions', 'code_counter', 105, 'number');

    $response = $this->postJson(route('expense-transactions.store'), $this->transactionPayload())->assertCreated();

    expect(expCodeInt($response->json('data.code')))->toBe(105);
    expect((int) ExpenseTransaction::getNextSuggestedCode())->toBe(106);
});

it('auto-creates code counter setting when missing', function () {
    Setting::where('group_name', 'expense_transactions')->where('key_name', 'code_counter')->delete();
    Setting::clearCache();

    $response = $this->postJson(route('expense-transactions.store'), $this->transactionPayload())->assertCreated();

    expect(expCodeInt($response->json('data.code')))->toBe(100);

    $setting = Setting::where('group_name', 'expense_transactions')->where('key_name', 'code_counter')->first();
    expect($setting)->not()->toBeNull()
        ->and($setting->data_type)->toBe('number')
        ->and((int) $setting->value)->toBe(101);
});

it('generates sequential codes for consecutive transactions', function () {
    ExpenseTransaction::withTrashed()->forceDelete();

    $response1 = $this->postJson(route('expense-transactions.store'), $this->transactionPayload(['amount' => 1000.00]))->assertCreated();
    $response2 = $this->postJson(route('expense-transactions.store'), $this->transactionPayload(['amount' => 2000.00]))->assertCreated();

    $code1 = expCodeInt($response1->json('data.code'));
    $code2 = expCodeInt($response2->json('data.code'));

    expect($code1)->toBeGreaterThanOrEqual(100)
        ->and($code2)->toBe($code1 + 1);
});

it('generates strictly increasing codes for concurrent transactions', function () {
    $transactions = [];
    for ($i = 0; $i < 5; $i++) {
        $transactions[] = $this->createTransaction();
    }

    $codes = collect($transactions)
        ->map(fn ($t) => expCodeInt($t->code))
        ->sort()
        ->values();

    for ($i = 1; $i < count($codes); $i++) {
        expect($codes[$i])->toBeGreaterThan($codes[$i - 1]);
    }
});
