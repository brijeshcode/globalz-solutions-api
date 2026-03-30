<?php

use App\Models\Customers\Customer;
use App\Models\Customers\CustomerPayment;
use Tests\Feature\Customers\PaymentOrders\Concerns\HasCustomerPaymentOrderSetup;

uses(HasCustomerPaymentOrderSetup::class);

beforeEach(function () {
    $this->setUpCustomerPaymentOrders();
    $this->actingAs($this->salesman, 'sanctum');
});

it('salesman creates a pending payment order', function () {
    $this->postJson(route('customers.payment-orders.store'), $this->paymentPayload())
        ->assertCreated()
        ->assertJsonStructure(['message', 'data' => ['id', 'code', 'payment_code', 'status', 'is_approved', 'is_pending', 'customer', 'currency']])
        ->assertJson(['data' => ['status' => 'pending', 'is_approved' => false, 'is_pending' => true]]);

    $payment = CustomerPayment::latest()->first();
    expect($payment->isPending())->toBeTrue()
        ->and($payment->approved_by)->toBeNull()
        ->and($payment->account_id)->toBeNull();

    $this->assertDatabaseHas('customer_payments', [
        'customer_id' => $this->customer->id,
        'approved_by' => null,
        'approved_at' => null,
    ]);
});

it('admin creates a pending payment order', function () {
    $this->actingAs($this->admin, 'sanctum');

    $this->postJson(route('customers.payment-orders.store'), $this->paymentPayload())
        ->assertCreated()
        ->assertJson(['data' => ['status' => 'pending', 'is_approved' => false, 'is_pending' => true]]);

    $payment = CustomerPayment::latest()->first();
    expect($payment->isPending())->toBeTrue()
        ->and($payment->approved_by)->toBeNull();
});

it('auto-generates a 6-digit code with matching payment_code', function () {
    $payment = $this->createOrderViaApi();

    expect($payment->code)->not()->toBeNull()
        ->and($payment->code)->toMatch('/^\d{6}$/')
        ->and($payment->payment_code)->toBe($payment->prefix . $payment->code);
});

it('sets created_by to the authenticated user', function () {
    $payment = $this->createOrderViaApi();

    expect($payment->created_by)->toBe($this->salesman->id)
        ->and($payment->updated_by)->toBe($this->salesman->id);
});

it('requires all mandatory fields', function () {
    $this->postJson(route('customers.payment-orders.store'), [
        'customer_id'   => null,
        'currency_id'   => null,
        'amount'        => -100,
        'currency_rate' => 0,
    ])->assertUnprocessable()
      ->assertJsonValidationErrors(['date', 'prefix', 'customer_id', 'currency_id', 'currency_rate', 'amount', 'amount_usd']);
});

it('rejects amount_usd that does not match currency rate calculation', function () {
    $this->postJson(route('customers.payment-orders.store'),
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

    $this->postJson(route('customers.payment-orders.store'),
        $this->paymentPayload(['customer_id' => $inactive->id])
    )->assertUnprocessable()
     ->assertJsonValidationErrors(['customer_id']);
});
