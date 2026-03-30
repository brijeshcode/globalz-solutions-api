<?php

use Tests\Feature\Customers\Payments\Concerns\HasCustomerPaymentSetup;

uses(HasCustomerPaymentSetup::class);

beforeEach(function () {
    $this->setUpCustomerPayments();
    $this->actingAs($this->admin, 'sanctum');
});

it('shows a payment with all relationships loaded', function () {
    $payment = $this->createPaymentViaApi();

    $this->getJson(route('customers.payments.show', $payment))
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
                'approved_by_user'      => ['id', 'name'],
                'account'               => ['id', 'name'],
            ],
        ]);
});

it('returns 404 for a non-existent payment', function () {
    $this->getJson(route('customers.payments.show', 999999))
        ->assertNotFound();
});
