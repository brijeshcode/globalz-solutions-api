<?php

use App\Models\Setups\Supplier;
use App\Models\Suppliers\SupplierPayment;
use App\Models\User;

beforeEach(function () {
    $this->setUpSupplierPayments();
});

it('creates a supplier payment successfully', function () {
    $this->postJson(route('suppliers.payments.store'), $this->paymentPayload([
        'supplier_order_number' => 'ORD-2025-001',
        'check_number'          => 'CHK-001',
        'bank_ref_number'       => 'BNK-REF-001',
        'note'                  => 'January settlement',
    ]))
        ->assertCreated()
        ->assertJsonStructure([
            'message',
            'data' => [
                'id', 'code', 'prefix', 'payment_code',
                'date', 'amount', 'amount_usd',
                'supplier', 'currency', 'account',
            ],
        ]);

    $this->assertDatabaseHas('supplier_payments', [
        'supplier_id'           => $this->supplier->id,
        'account_id'            => $this->account->id,
        'supplier_order_number' => 'ORD-2025-001',
    ]);
});

it('auto-generates code with SPAY prefix', function () {
    $payment = $this->createPaymentViaApi();

    expect($payment->prefix)->toBe('SPAY')
        ->and($payment->code)->not()->toBeNull()
        ->and($payment->payment_code)->toStartWith('SPAY');
});

it('sets created_by and updated_by automatically', function () {
    $payment = $this->createPaymentViaApi();

    expect($payment->created_by)->toBe($this->user->id)
        ->and($payment->updated_by)->toBe($this->user->id);
});

it('reduces account current_balance on payment creation', function () {
    $balanceBefore = (float) $this->account->fresh()->current_balance;

    $this->createPaymentViaApi(['amount' => 500.00, 'amount_usd' => 500.00]);

    $balanceAfter = (float) $this->account->fresh()->current_balance;

    expect($balanceAfter)->toBe($balanceBefore - 500.00);
});

it('reduces supplier current_balance on payment creation', function () {
    $balanceBefore = (float) $this->supplier->fresh()->current_balance;

    $this->createPaymentViaApi(['amount' => 300.00, 'amount_usd' => 300.00]);

    $balanceAfter = (float) $this->supplier->fresh()->current_balance;

    expect($balanceAfter)->toBe($balanceBefore - 300.00);
});

it('correctly reduces both account and supplier balances for multiple payments', function () {
    $this->createPaymentViaApi(['amount' => 200.00, 'amount_usd' => 200.00]);
    $this->createPaymentViaApi(['amount' => 350.00, 'amount_usd' => 350.00]);

    // Account: 10000 - 200 - 350 = 9450
    expect((float) $this->account->fresh()->current_balance)->toBe(9450.00);

    // Supplier: 0 - 200 - 350 = -550
    expect((float) $this->supplier->fresh()->current_balance)->toBe(-550.00);
});

it('validates required fields', function () {
    $this->postJson(route('suppliers.payments.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'date',
            'supplier_id',
            'account_id',
            'currency_id',
            'currency_rate',
            'amount',
            'amount_usd',
        ]);
});

it('validates that amount must be greater than zero', function () {
    $this->postJson(route('suppliers.payments.store'), $this->paymentPayload([
        'amount'     => 0,
        'amount_usd' => 0,
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount', 'amount_usd']);
});

it('rejects payment for an inactive supplier', function () {
    $inactiveSupplier = Supplier::factory()->inactive()->create();

    $this->postJson(route('suppliers.payments.store'), $this->paymentPayload([
        'supplier_id' => $inactiveSupplier->id,
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['supplier_id']);
});

it('rejects non-existent supplier and account', function () {
    $this->postJson(route('suppliers.payments.store'), $this->paymentPayload([
        'supplier_id' => 99999,
        'account_id'  => 99999,
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['supplier_id', 'account_id']);
});

it('admin role can create a payment', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->actingAs($admin, 'sanctum');

    $this->postJson(route('suppliers.payments.store'), $this->paymentPayload())
        ->assertCreated();
});

it('salesman role cannot create a payment', function () {
    $salesman = User::factory()->create(['role' => User::ROLE_SALESMAN]);
    $this->actingAs($salesman, 'sanctum');

    $this->postJson(route('suppliers.payments.store'), $this->paymentPayload())
        ->assertForbidden();
});

it('warehouse manager role cannot create a payment', function () {
    $manager = User::factory()->create(['role' => User::ROLE_WAREHOUSE_MANAGER]);
    $this->actingAs($manager, 'sanctum');

    $this->postJson(route('suppliers.payments.store'), $this->paymentPayload())
        ->assertForbidden();
});
