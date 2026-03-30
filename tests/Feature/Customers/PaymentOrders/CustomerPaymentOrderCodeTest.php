<?php

use App\Models\Customers\CustomerPayment;
use App\Models\Setting;
use Tests\Feature\Customers\PaymentOrders\Concerns\HasCustomerPaymentOrderSetup;

uses(HasCustomerPaymentOrderSetup::class);

beforeEach(function () {
    $this->setUpCustomerPaymentOrders();
    $this->actingAs($this->salesman, 'sanctum');
});

it('auto-generates a 6-digit code on creation', function () {
    $payment = $this->createOrderViaApi();

    expect($payment->code)->not()->toBeNull()
        ->and($payment->code)->toMatch('/^\d{6}$/')
        ->and($payment->payment_code)->toBe($payment->prefix . $payment->code);
});

it('generates the correct code for a known counter value', function () {
    Setting::where('group_name', 'customer_payments')
        ->where('key_name', 'code_counter')
        ->update(['value' => '1005']);

    $code = $this->postJson(route('customers.payment-orders.store'), $this->paymentPayload())
        ->assertCreated()
        ->json('data.code');

    expect($code)->toBe('001006');
});

it('increments the counter sequentially', function () {
    // Use direct model creation to test the increment in isolation
    Setting::where('group_name', 'customer_payments')
        ->where('key_name', 'code_counter')
        ->update(['value' => '999']);

    $payment1 = CustomerPayment::create($this->paymentPayload(['rtc_book_number' => 'RTC-SEQ-1']));
    $payment2 = CustomerPayment::create($this->paymentPayload(['rtc_book_number' => 'RTC-SEQ-2']));

    expect((int) $payment1->code)->toBeGreaterThanOrEqual(1000)
        ->and((int) $payment2->code)->toBe((int) $payment1->code + 1);
});
