<?php

use App\Models\Accounts\Account;
use App\Models\Setups\Supplier;
use App\Models\User;

beforeEach(function () {
    $this->setUpSupplierPayments();
});

it('updates payment fields successfully', function () {
    $payment = $this->createPaymentViaApi(['amount' => 500.00, 'amount_usd' => 500.00]);

    $this->putJson(route('suppliers.payments.update', $payment), $this->paymentPayload([
        'amount'                => 500.00,
        'amount_usd'            => 500.00,
        'supplier_order_number' => 'ORD-UPDATED',
        'check_number'          => 'CHK-UPDATED',
        'note'                  => 'Updated note',
    ]))->assertOk();

    $payment->refresh();
    expect($payment->supplier_order_number)->toBe('ORD-UPDATED')
        ->and($payment->check_number)->toBe('CHK-UPDATED')
        ->and($payment->note)->toBe('Updated note');
});

it('adjusts account balance when payment amount increases', function () {
    $payment = $this->createPaymentViaApi(['amount' => 500.00, 'amount_usd' => 500.00]);

    // Account: 10000 - 500 = 9500 after creation
    $balanceAfterCreate = (float) $this->account->fresh()->current_balance;

    // Increase amount to 800
    $this->putJson(route('suppliers.payments.update', $payment), $this->paymentPayload([
        'amount'     => 800.00,
        'amount_usd' => 800.00,
    ]))->assertOk();

    // Difference = 800 - 500 = 300 more removed → 9500 - 300 = 9200
    expect((float) $this->account->fresh()->current_balance)->toBe($balanceAfterCreate - 300.00);
});

it('adjusts account balance when payment amount decreases', function () {
    $payment = $this->createPaymentViaApi(['amount' => 500.00, 'amount_usd' => 500.00]);

    $balanceAfterCreate = (float) $this->account->fresh()->current_balance;

    // Decrease amount to 200
    $this->putJson(route('suppliers.payments.update', $payment), $this->paymentPayload([
        'amount'     => 200.00,
        'amount_usd' => 200.00,
    ]))->assertOk();

    // Difference = 200 - 500 = -300 → removeBalance(-300) = addBalance(300) → 9500 + 300 = 9800
    expect((float) $this->account->fresh()->current_balance)->toBe($balanceAfterCreate + 300.00);
});

it('adjusts supplier balance when payment amount increases', function () {
    $payment = $this->createPaymentViaApi(['amount' => 400.00, 'amount_usd' => 400.00]);

    $supplierBalanceAfterCreate = (float) $this->supplier->fresh()->current_balance;

    $this->putJson(route('suppliers.payments.update', $payment), $this->paymentPayload([
        'amount'     => 600.00,
        'amount_usd' => 600.00,
    ]))->assertOk();

    // Difference = 600 - 400 = 200 more removed from supplier
    expect((float) $this->supplier->fresh()->current_balance)->toBe($supplierBalanceAfterCreate - 200.00);
});

it('restores old account balance and charges new account when account changes', function () {
    $newAccount = Account::factory()->create(['current_balance' => 5000, 'is_active' => true]);

    $payment = $this->createPaymentViaApi(['amount' => 500.00, 'amount_usd' => 500.00]);

    // After creation: old account = 10000 - 500 = 9500, new account = 5000
    $oldAccountBalance = (float) $this->account->fresh()->current_balance;
    $newAccountBalance = (float) $newAccount->fresh()->current_balance;

    $this->putJson(route('suppliers.payments.update', $payment), $this->paymentPayload([
        'account_id' => $newAccount->id,
        'amount'     => 500.00,
        'amount_usd' => 500.00,
    ]))->assertOk();

    // Old account: 9500 + 500 = 10000 (restored)
    expect((float) $this->account->fresh()->current_balance)->toBe($oldAccountBalance + 500.00);

    // New account: 5000 - 500 = 4500
    expect((float) $newAccount->fresh()->current_balance)->toBe($newAccountBalance - 500.00);
});

it('restores old supplier balance and charges new supplier when supplier changes', function () {
    $newSupplier = Supplier::factory()->active()->create(['current_balance' => 1000]);

    $payment = $this->createPaymentViaApi(['amount' => 300.00, 'amount_usd' => 300.00]);

    // After creation: old supplier = 0 - 300 = -300, new supplier = 1000
    $oldSupplierBalance = (float) $this->supplier->fresh()->current_balance;
    $newSupplierBalance = (float) $newSupplier->fresh()->current_balance;

    $this->putJson(route('suppliers.payments.update', $payment), $this->paymentPayload([
        'supplier_id' => $newSupplier->id,
        'amount'      => 300.00,
        'amount_usd'  => 300.00,
    ]))->assertOk();

    // Old supplier: -300 + 300 = 0 (restored)
    expect((float) $this->supplier->fresh()->current_balance)->toBe($oldSupplierBalance + 300.00);

    // New supplier: 1000 - 300 = 700
    expect((float) $newSupplier->fresh()->current_balance)->toBe($newSupplierBalance - 300.00);
});

it('salesman role cannot update a payment', function () {
    $payment = $this->createPaymentViaApi();

    $this->actingAs(User::factory()->create(['role' => User::ROLE_SALESMAN]), 'sanctum');

    $this->putJson(route('suppliers.payments.update', $payment), $this->paymentPayload())
        ->assertForbidden();
});

it('warehouse manager role cannot update a payment', function () {
    $payment = $this->createPaymentViaApi();

    $this->actingAs(User::factory()->create(['role' => User::ROLE_WAREHOUSE_MANAGER]), 'sanctum');

    $this->putJson(route('suppliers.payments.update', $payment), $this->paymentPayload())
        ->assertForbidden();
});

it('admin role can update a payment', function () {
    $payment = $this->createPaymentViaApi();

    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]), 'sanctum');

    $this->putJson(route('suppliers.payments.update', $payment), $this->paymentPayload([
        'amount'     => 500.00,
        'amount_usd' => 500.00,
        'note'       => 'Admin updated',
    ]))->assertOk();
});
