<?php

use Tests\Feature\Customers\PaymentOrders\Concerns\HasCustomerPaymentOrderSetup;

uses(HasCustomerPaymentOrderSetup::class);

// Approval actions are admin-only, so default to admin for this file
beforeEach(function () {
    $this->setUpCustomerPaymentOrders();
    $this->actingAs($this->admin, 'sanctum');
});

it('admin approves a pending payment order', function () {
    $payment = $this->createOrderViaFactory('pending');

    $this->patchJson(route('customers.payment-orders.approve', $payment), [
        'account_id'   => $this->account->id,
        'approve_note' => 'Payment order approved by admin',
    ])->assertOk()
      ->assertJson(['data' => ['status' => 'approved', 'is_approved' => true, 'is_pending' => false]]);

    $payment->refresh();
    expect($payment->isApproved())->toBeTrue()
        ->and($payment->approved_by)->toBe($this->admin->id)
        ->and($payment->account_id)->toBe($this->account->id)
        ->and($payment->approve_note)->toBe('Payment order approved by admin');
});

it('salesman cannot approve a payment order', function () {
    $this->actingAs($this->salesman, 'sanctum');
    $payment = $this->createOrderViaFactory('pending');

    $this->patchJson(route('customers.payment-orders.approve', $payment), [
        'account_id'   => $this->account->id,
        'approve_note' => 'Trying to approve as salesman',
    ])->assertForbidden()
      ->assertJson(['message' => 'You do not have permission to approve payments']);
});

it('cannot approve an already approved payment order', function () {
    $payment = $this->createOrderViaFactory('approved');

    $this->patchJson(route('customers.payment-orders.approve', $payment), [
        'account_id'   => $this->account->id,
        'approve_note' => 'Trying to approve again',
    ])->assertUnprocessable()
      ->assertJson(['message' => 'Payment is already approved']);
});

it('requires account_id to approve', function () {
    $payment = $this->createOrderViaFactory('pending');

    $this->patchJson(route('customers.payment-orders.approve', $payment), [
        'approve_note' => 'Missing account_id',
    ])->assertUnprocessable()
      ->assertJsonValidationErrors(['account_id']);
});
