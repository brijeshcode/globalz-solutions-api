<?php

use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\Supplier;

beforeEach(function () {
    $this->setUpSupplierPayments();
});

it('lists payments with correct structure', function () {
    $this->createPaymentViaApi();
    $this->createPaymentViaApi(['supplier_order_number' => 'ORD-002']);
    $this->createPaymentViaApi(['supplier_order_number' => 'ORD-003']);

    $this->getJson(route('suppliers.payments.index'))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                '*' => [
                    'id', 'code', 'date', 'prefix', 'payment_code',
                    'amount', 'amount_usd', 'supplier', 'currency', 'account',
                ],
            ],
            'pagination',
        ])
        ->assertJsonCount(3, 'data');
});

it('filters payments by supplier', function () {
    $otherSupplier = Supplier::factory()->active()->create();

    $this->createPaymentViaApi();
    $this->createPaymentViaApi(['supplier_id' => $otherSupplier->id]);

    $this->getJson(route('suppliers.payments.index', ['supplier_id' => $this->supplier->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters payments by currency', function () {
    $otherCurrency = Currency::factory()->create(['is_active' => true]);

    $this->createPaymentViaApi();
    $this->createPaymentViaApi([
        'currency_id' => $otherCurrency->id,
        'amount'      => 500.00,
        'amount_usd'  => 500.00,
    ]);

    $this->getJson(route('suppliers.payments.index', ['currency_id' => $this->currency->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters payments by prefix', function () {
    // All payments get prefix SPAY automatically — create a payment and verify filter
    $this->createPaymentViaApi();

    $this->getJson(route('suppliers.payments.index', ['prefix' => 'SPAY']))
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson(route('suppliers.payments.index', ['prefix' => 'XXXX']))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('filters payments by date range', function () {
    $this->createPaymentViaApi(['date' => '2025-01-10']);
    $this->createPaymentViaApi(['date' => '2025-02-15']);
    $this->createPaymentViaApi(['date' => '2025-03-20']);

    $this->getJson(route('suppliers.payments.index', ['start_date' => '2025-02-01', 'end_date' => '2025-02-28']))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('paginates results', function () {
    for ($i = 0; $i < 7; $i++) {
        $this->createPaymentViaApi(['supplier_order_number' => "ORD-{$i}"]);
    }

    $response = $this->getJson(route('suppliers.payments.index', ['per_page' => 3]))->assertOk();

    expect($response->json('data'))->toHaveCount(3)
        ->and($response->json('pagination.total'))->toBe(7)
        ->and($response->json('pagination.per_page'))->toBe(3)
        ->and($response->json('pagination.last_page'))->toBe(3);
});

it('searches payments by note', function () {
    $this->createPaymentViaApi(['note' => 'Invoice settlement for January']);
    $this->createPaymentViaApi(['note' => 'Advance payment']);

    $data = $this->getJson(route('suppliers.payments.index', ['search' => 'January']))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['note'])->toBe('Invoice settlement for January');
});
