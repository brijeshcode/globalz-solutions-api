<?php

use Tests\Feature\Customers\PaymentOrders\Concerns\HasCustomerPaymentOrderSetup;

uses(HasCustomerPaymentOrderSetup::class);

beforeEach(function () {
    $this->setUpCustomerPaymentOrders();
    $this->actingAs($this->salesman, 'sanctum');
});

it('shows a payment order with all relationships loaded', function () {
    $payment = $this->createOrderViaApi();

    $this->getJson(route('customers.payment-orders.show', $payment))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'id',
                'payment_code',
                'customer'              => ['id', 'name', 'code'],
                'currency'              => ['id', 'name', 'code', 'symbol'],
                'customer_payment_term' => ['id', 'name', 'days'],
                'created_by_user'       => ['id', 'name'],
            ],
        ]);
});

it('returns 404 for a non-existent payment order', function () {
    $this->getJson(route('customers.payment-orders.show', 999999))
        ->assertNotFound();
});
