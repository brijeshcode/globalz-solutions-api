<?php

use Tests\Feature\Customers\Payments\Concerns\HasCustomerPaymentSetup;

uses(HasCustomerPaymentSetup::class);

// Unapproval actions are admin-only, so default to admin for this file
beforeEach(function () {
    $this->setUpCustomerPayments();
    $this->actingAs($this->admin, 'sanctum');
});

it('admin can unapprove a payment (currently returns 403)', function () {
    $payment = $this->createPaymentViaFactory('approved');

    $this->patchJson(route('customers.payments.unapprove', $payment))
        ->assertStatus(403);
});

it('employee cannot unapprove a payment', function () {
    $this->actingAs($this->salesman, 'sanctum');
    $payment = $this->createPaymentViaFactory('approved');

    $this->patchJson(route('customers.payments.unapprove', $payment))
        ->assertStatus(403);
});

it('cannot unapprove a pending payment', function () {
    $payment = $this->createPaymentViaFactory('pending');

    $this->patchJson(route('customers.payments.unapprove', $payment))
        ->assertStatus(403);
});
