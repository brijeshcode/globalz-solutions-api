<?php

use Tests\Feature\Customers\PaymentOrders\Concerns\HasCustomerPaymentOrderSetup;

uses(HasCustomerPaymentOrderSetup::class);

beforeEach(function () {
    $this->setUpCustomerPaymentOrders();
    $this->actingAs($this->salesman, 'sanctum');
});

it('salesman can update a pending payment order', function () {
    $payment = $this->createOrderViaApi();

    $this->putJson(route('customers.payment-orders.update', $payment), $this->paymentPayload([
        'date'            => '2025-01-20',
        'note'            => 'Updated payment order note',
        'rtc_book_number' => 'RTC-UPDATED-' . uniqid(),
    ]))->assertOk()
       ->assertJson(['data' => ['amount' => 1000.00, 'note' => 'Updated payment order note']]);

    $this->assertDatabaseHas('customer_payments', [
        'id'   => $payment->id,
        'note' => 'Updated payment order note',
    ]);
});

it('sets updated_by to the authenticated user on update', function () {
    $payment = $this->createOrderViaApi();

    $this->putJson(route('customers.payment-orders.update', $payment), $this->paymentPayload([
        'rtc_book_number' => 'RTC-UPD-' . uniqid(),
    ]))->assertOk();

    expect($payment->fresh()->updated_by)->toBe($this->salesman->id);
});

it('cannot update an approved payment order', function () {
    $this->actingAs($this->admin, 'sanctum');
    $payment = $this->createOrderViaFactory('approved');

    $this->putJson(route('customers.payment-orders.update', $payment), $this->paymentPayload([
        'amount'          => 2000.00,
        'amount_usd'      => 2500.00,
        'rtc_book_number' => 'RTC-ATTEMPT-' . uniqid(),
    ]))->assertUnprocessable()
       ->assertJson(['message' => 'Cannot update approved payments']);
});
