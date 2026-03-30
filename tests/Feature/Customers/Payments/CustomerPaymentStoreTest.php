<?php

use App\Models\Customers\Customer;
use App\Models\Customers\CustomerPayment;
use Tests\Feature\Customers\Payments\Concerns\HasCustomerPaymentSetup;

uses(HasCustomerPaymentSetup::class);

beforeEach(function () {
    $this->setUpCustomerPayments();
    $this->actingAs($this->admin, 'sanctum');
});

it('admin creates an approved payment', function () {
    $this->postJson(route('customers.payments.store'), $this->paymentPayload([
        'approve_note' => 'Admin approved during creation',
    ]))->assertCreated()
      ->assertJson(['data' => ['status' => 'approved', 'is_approved' => true, 'is_pending' => false]]);

    $payment = CustomerPayment::latest()->first();
    expect($payment->isApproved())->toBeTrue()
        ->and($payment->approved_by)->toBe($this->admin->id)
        ->and($payment->account_id)->toBe($this->account->id);
});

it('sets created_by and updated_by to the authenticated user', function () {
    $payment = $this->createPaymentViaApi();

    expect($payment->created_by)->toBe($this->admin->id)
        ->and($payment->updated_by)->toBe($this->admin->id);
});

it('requires all mandatory fields', function () {
    $this->postJson(route('customers.payments.store'), [
        'customer_id'    => null,
        'currency_id'    => null,
        'amount'         => -100,
        'currency_rate'  => 0,
        'rtc_book_number' => '',
    ])->assertUnprocessable()
      ->assertJsonValidationErrors(['date', 'prefix', 'customer_id', 'currency_id', 'currency_rate', 'amount', 'amount_usd', 'account_id']);
});

it('rejects amount_usd that does not match currency rate calculation', function () {
    $this->postJson(route('customers.payments.store'),
        $this->paymentPayload(['amount' => 1000.00, 'currency_rate' => 2.0, 'amount_usd' => 600.00])
    )->assertUnprocessable()
     ->assertJsonValidationErrors(['amount_usd']);
});

it('rejects an inactive customer', function () {
    $inactive = Customer::factory()->create([
        'is_active'  => false,
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $this->postJson(route('customers.payments.store'),
        $this->paymentPayload(['customer_id' => $inactive->id])
    )->assertUnprocessable()
     ->assertJsonValidationErrors(['customer_id']);
});
