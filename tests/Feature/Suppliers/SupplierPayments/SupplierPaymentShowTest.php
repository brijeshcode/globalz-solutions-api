<?php

beforeEach(function () {
    $this->setUpSupplierPayments();
});

it('shows payment with all relationships', function () {
    $payment = $this->createPaymentViaApi([
        'supplier_order_number' => 'ORD-SHOW-001',
        'note'                  => 'Show test payment',
    ]);

    $this->getJson(route('suppliers.payments.show', $payment))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'id',
                'code',
                'prefix',
                'payment_code',
                'date',
                'amount',
                'amount_usd',
                'currency_rate',
                'supplier_order_number',
                'note',
                'supplier'  => ['id', 'name', 'code'],
                'currency'  => ['id', 'name', 'code', 'symbol'],
                'account'   => ['id', 'name'],
                'created_by_user' => ['id', 'name'],
            ],
        ]);
});

it('returns the correct payment data', function () {
    $payment = $this->createPaymentViaApi([
        'amount'     => 750.00,
        'amount_usd' => 750.00,
        'note'       => 'Specific payment',
    ]);

    $data = $this->getJson(route('suppliers.payments.show', $payment))
        ->assertOk()
        ->json('data');

    expect($data['id'])->toBe($payment->id)
        ->and((float) $data['amount'])->toBe(750.00)
        ->and($data['prefix'])->toBe('SPAY')
        ->and($data['supplier']['id'])->toBe($this->supplier->id)
        ->and($data['account']['id'])->toBe($this->account->id);
});

it('returns 404 for a non-existent payment', function () {
    $this->getJson(route('suppliers.payments.show', 99999))->assertNotFound();
});
