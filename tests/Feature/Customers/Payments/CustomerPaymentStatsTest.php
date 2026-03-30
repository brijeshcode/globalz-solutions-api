<?php

use Tests\Feature\Customers\Payments\Concerns\HasCustomerPaymentSetup;

uses(HasCustomerPaymentSetup::class);

beforeEach(function () {
    $this->setUpCustomerPayments();
    $this->actingAs($this->admin, 'sanctum');
});

it('returns correct payment statistics', function () {
    for ($i = 0; $i < 3; $i++) {
        $this->createPaymentViaFactory('pending');
    }
    for ($i = 0; $i < 2; $i++) {
        $this->createPaymentViaFactory('approved');
    }
    // Soft-deleted approved payment — should not count in totals
    $this->createPaymentViaFactory('approved', ['deleted_at' => now()]);

    $stats = $this->getJson(route('customers.payments.stats'))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => ['total_payments', 'total_amount_usd'],
        ])
        ->json('data');

    expect($stats['total_payments'])->toBe(2);
});
