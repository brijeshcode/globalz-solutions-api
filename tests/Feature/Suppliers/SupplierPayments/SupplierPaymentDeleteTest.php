<?php

use App\Models\User;

beforeEach(function () {
    $this->setUpSupplierPayments();
});

it('super_admin can soft delete a payment', function () {
    $payment = $this->createPaymentViaApi();

    $this->deleteJson(route('suppliers.payments.destroy', $payment))->assertStatus(204);

    $this->assertSoftDeleted('supplier_payments', ['id' => $payment->id]);
});

it('admin role cannot delete a payment', function () {
    $payment = $this->createPaymentViaApi();

    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]), 'sanctum');

    $this->deleteJson(route('suppliers.payments.destroy', $payment))->assertForbidden();
});

it('salesman role cannot delete a payment', function () {
    $payment = $this->createPaymentViaApi();

    $this->actingAs(User::factory()->create(['role' => User::ROLE_SALESMAN]), 'sanctum');

    $this->deleteJson(route('suppliers.payments.destroy', $payment))->assertForbidden();
});

it('restores account balance when payment is soft deleted', function () {
    $payment = $this->createPaymentViaApi(['amount' => 500.00, 'amount_usd' => 500.00]);

    // Account balance after creation: 10000 - 500 = 9500
    $balanceAfterCreate = (float) $this->account->fresh()->current_balance;

    $this->deleteJson(route('suppliers.payments.destroy', $payment))->assertStatus(204);

    // On delete, 500 is added back → 9500 + 500 = 10000
    expect((float) $this->account->fresh()->current_balance)->toBe($balanceAfterCreate + 500.00);
});

it('restores supplier balance when payment is soft deleted', function () {
    $payment = $this->createPaymentViaApi(['amount' => 400.00, 'amount_usd' => 400.00]);

    // Supplier balance after creation: 0 - 400 = -400
    $supplierBalanceAfterCreate = (float) $this->supplier->fresh()->current_balance;

    $this->deleteJson(route('suppliers.payments.destroy', $payment))->assertStatus(204);

    // On delete, 400 is added back → -400 + 400 = 0
    expect((float) $this->supplier->fresh()->current_balance)->toBe($supplierBalanceAfterCreate + 400.00);
});

it('lists trashed payments', function () {
    $payment = $this->createPaymentViaApi();

    $this->deleteJson(route('suppliers.payments.destroy', $payment))->assertStatus(204);

    $this->getJson(route('suppliers.payments.trashed'))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('trashed list does not include non-deleted payments', function () {
    $this->createPaymentViaApi();
    $deleted = $this->createPaymentViaApi(['supplier_order_number' => 'TO-DELETE']);
    $this->deleteJson(route('suppliers.payments.destroy', $deleted))->assertStatus(204);

    $this->getJson(route('suppliers.payments.trashed'))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('restores a trashed payment', function () {
    $payment = $this->createPaymentViaApi();
    $this->deleteJson(route('suppliers.payments.destroy', $payment))->assertStatus(204);

    $this->patchJson(route('suppliers.payments.restore', $payment->id))->assertOk();

    $this->assertDatabaseHas('supplier_payments', [
        'id'         => $payment->id,
        'deleted_at' => null,
    ]);
});

it('force deletes a payment permanently', function () {
    $payment = $this->createPaymentViaApi();
    $this->deleteJson(route('suppliers.payments.destroy', $payment))->assertStatus(204);

    $this->deleteJson(route('suppliers.payments.force-delete', $payment->id))->assertStatus(204);

    $this->assertDatabaseMissing('supplier_payments', ['id' => $payment->id]);
});

it('re-deducts account balance when a trashed payment is restored', function () {
    $payment = $this->createPaymentViaApi(['amount' => 500.00, 'amount_usd' => 500.00]);

    // After creation account = 10000 - 500 = 9500
    $this->deleteJson(route('suppliers.payments.destroy', $payment))->assertStatus(204);
    // After soft-delete account = 9500 + 500 = 10000 (restored)

    $balanceBeforeRestore = (float) $this->account->fresh()->current_balance;

    $this->patchJson(route('suppliers.payments.restore', $payment->id))->assertOk();

    // After restore, payment is active again — account balance should be re-deducted
    expect((float) $this->account->fresh()->current_balance)->toBe($balanceBeforeRestore - 500.00);
});

it('re-deducts supplier balance when a trashed payment is restored', function () {
    $payment = $this->createPaymentViaApi(['amount' => 300.00, 'amount_usd' => 300.00]);

    // After creation supplier = 0 - 300 = -300
    $this->deleteJson(route('suppliers.payments.destroy', $payment))->assertStatus(204);
    // After soft-delete supplier = -300 + 300 = 0

    $balanceBeforeRestore = (float) $this->supplier->fresh()->current_balance;

    $this->patchJson(route('suppliers.payments.restore', $payment->id))->assertOk();

    // After restore, payment is active again — supplier balance should be re-deducted
    expect((float) $this->supplier->fresh()->current_balance)->toBe($balanceBeforeRestore - 300.00);
});

it('filters trashed payments by supplier', function () {
    $otherSupplier = \App\Models\Setups\Supplier::factory()->active()->create();

    $p1 = $this->createPaymentViaApi();
    $p2 = $this->createPaymentViaApi(['supplier_id' => $otherSupplier->id, 'amount' => 200.00, 'amount_usd' => 200.00]);

    $this->deleteJson(route('suppliers.payments.destroy', $p1))->assertStatus(204);
    $this->deleteJson(route('suppliers.payments.destroy', $p2))->assertStatus(204);

    $this->getJson(route('suppliers.payments.trashed', ['supplier_id' => $this->supplier->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters trashed payments by currency', function () {
    $otherCurrency = \App\Models\Setups\Generals\Currencies\Currency::factory()->create(['is_active' => true]);

    $p1 = $this->createPaymentViaApi();
    $p2 = $this->createPaymentViaApi(['currency_id' => $otherCurrency->id, 'amount' => 200.00, 'amount_usd' => 200.00]);

    $this->deleteJson(route('suppliers.payments.destroy', $p1))->assertStatus(204);
    $this->deleteJson(route('suppliers.payments.destroy', $p2))->assertStatus(204);

    $this->getJson(route('suppliers.payments.trashed', ['currency_id' => $this->currency->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
