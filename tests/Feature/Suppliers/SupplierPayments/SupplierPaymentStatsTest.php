<?php

use App\Models\Setups\Supplier;

beforeEach(function () {
    $this->setUpSupplierPayments();
});

it('returns correct payment count and total amount', function () {
    $this->createPaymentViaApi(['amount' => 200.00, 'amount_usd' => 200.00]);
    $this->createPaymentViaApi(['amount' => 300.00, 'amount_usd' => 300.00]);
    $this->createPaymentViaApi(['amount' => 500.00, 'amount_usd' => 500.00]);

    $data = $this->getJson(route('suppliers.payments.stats'))->assertOk()->json('data');

    expect($data['total_payments'])->toBe(3)
        ->and((float) $data['total_amount_usd'])->toBe(1000.00);
});

it('stats reflect correct total after multiple payments', function () {
    $this->createPaymentViaApi(['amount' => 1000.00, 'amount_usd' => 1000.00]);

    $data = $this->getJson(route('suppliers.payments.stats'))->assertOk()->json('data');

    expect($data['total_payments'])->toBe(1)
        ->and((float) $data['total_amount_usd'])->toBe(1000.00);
});

it('stats can be filtered by supplier', function () {
    $otherSupplier = Supplier::factory()->active()->create();

    $this->createPaymentViaApi(['amount' => 400.00, 'amount_usd' => 400.00]);
    $this->createPaymentViaApi([
        'supplier_id' => $otherSupplier->id,
        'amount'      => 600.00,
        'amount_usd'  => 600.00,
    ]);

    $data = $this->getJson(route('suppliers.payments.stats', ['supplier_id' => $this->supplier->id]))
        ->assertOk()
        ->json('data');

    expect($data['total_payments'])->toBe(1)
        ->and((float) $data['total_amount_usd'])->toBe(400.00);
});

it('stats return zero when no payments exist', function () {
    $data = $this->getJson(route('suppliers.payments.stats'))->assertOk()->json('data');

    expect($data['total_payments'])->toBe(0)
        ->and((float) $data['total_amount_usd'])->toBe(0.0);
});
