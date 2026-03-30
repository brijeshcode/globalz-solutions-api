<?php

use Tests\Feature\Customers\Payments\Concerns\HasCustomerPaymentSetup;

uses(HasCustomerPaymentSetup::class);

beforeEach(function () {
    $this->setUpCustomerPayments();
    $this->actingAs($this->admin, 'sanctum');
});

it('cannot update an approved payment', function () {
    $payment = $this->createPaymentViaFactory('approved');

    $this->putJson(route('customers.payments.update', $payment), $this->paymentPayload([
        'amount'          => 2000.00,
        'amount_usd'      => 1600.00,
        'rtc_book_number' => 'RTC-UPDATE-ATTEMPT-' . uniqid(),
    ]))->assertUnprocessable()
       ->assertJson(['message' => 'Cannot update approved payments']);
});
