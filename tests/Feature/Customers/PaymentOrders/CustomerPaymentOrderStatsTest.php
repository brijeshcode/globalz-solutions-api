<?php

use Tests\Feature\Customers\PaymentOrders\Concerns\HasCustomerPaymentOrderSetup;

uses(HasCustomerPaymentOrderSetup::class);

beforeEach(function () {
    $this->setUpCustomerPaymentOrders();
    $this->actingAs($this->salesman, 'sanctum');
});

it('returns correct payment statistics', function () {
    for ($i = 0; $i < 3; $i++) {
        $this->createOrderViaFactory('pending');
    }
    for ($i = 0; $i < 2; $i++) {
        $this->createOrderViaFactory('approved');
    }
    // Soft-deleted approved payment — should not count in totals
    $this->createOrderViaFactory('approved', ['deleted_at' => now()]);

    $stats = $this->getJson(route('customers.payment-orders.stats'))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => ['total_payments', 'total_amount_usd'],
        ])
        ->json('data');

    expect($stats['total_payments'])->toBe(3);
});
