<?php

use App\Models\Customers\Customer;
use App\Models\Setups\Generals\Currencies\Currency;
use Tests\Feature\Customers\Payments\Concerns\HasCustomerPaymentSetup;

uses(HasCustomerPaymentSetup::class);

beforeEach(function () {
    $this->setUpCustomerPayments();
    $this->actingAs($this->admin, 'sanctum');
});

it('lists approved payments with correct structure', function () {
    for ($i = 0; $i < 2; $i++) {
        $this->createPaymentViaFactory('pending');
    }
    for ($i = 0; $i < 3; $i++) {
        $this->createPaymentViaFactory('approved');
    }

    $response = $this->getJson(route('customers.payments.index'))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => ['*' => ['id', 'code', 'payment_code', 'date', 'prefix', 'amount', 'amount_usd', 'status', 'is_approved', 'is_pending', 'customer', 'currency']],
            'pagination',
        ]);

    expect($response->json('data'))->toHaveCount(3);
    foreach ($response->json('data') as $payment) {
        expect($payment['is_approved'])->toBe(true);
    }
});

it('salesman only sees approved payments for their own customers', function () {
    $otherCustomer = Customer::factory()->create([
        'salesperson_id' => null,
        'is_active'      => true,
    ]);

    $this->createPaymentViaFactory('approved', ['customer_id' => $this->customer->id]);
    $this->createPaymentViaFactory('approved', ['customer_id' => $this->customer->id]);
    $this->createPaymentViaFactory('approved', ['customer_id' => $otherCustomer->id]);
    $this->createPaymentViaFactory('pending', ['customer_id' => $this->customer->id]);

    $this->actingAs($this->salesman, 'sanctum');
    $response = $this->getJson(route('customers.payments.index'))->assertOk();
    $data = $response->json('data');

    expect($data)->toHaveCount(2);
    foreach ($data as $payment) {
        expect($payment['is_approved'])->toBe(true);
        expect($payment['customer']['id'])->toBe($this->customer->id);
    }
});

it('filters by customer', function () {
    $other = Customer::factory()->create(['created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);

    for ($i = 0; $i < 2; $i++) {
        $this->createPaymentViaFactory('approved', ['customer_id' => $this->customer->id]);
    }
    for ($i = 0; $i < 3; $i++) {
        $this->createPaymentViaFactory('approved', ['customer_id' => $other->id]);
    }

    $this->getJson(route('customers.payments.index', ['customer_id' => $this->customer->id]))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('filters by currency', function () {
    $other = Currency::factory()->eur()->create(['is_active' => true]);

    for ($i = 0; $i < 2; $i++) {
        $this->createPaymentViaFactory('approved', ['currency_id' => $this->currency->id]);
    }
    for ($i = 0; $i < 3; $i++) {
        $this->createPaymentViaFactory('approved', ['currency_id' => $other->id]);
    }

    $this->getJson(route('customers.payments.index', ['currency_id' => $this->currency->id]))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('filters by date range', function () {
    $this->createPaymentViaFactory('approved', ['date' => '2025-01-01']);
    $this->createPaymentViaFactory('approved', ['date' => '2025-02-15']);
    $this->createPaymentViaFactory('approved', ['date' => '2025-03-30']);

    $this->getJson(route('customers.payments.index', ['date_from' => '2025-02-01', 'date_to' => '2025-02-28']))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('searches by code', function () {
    $this->createPaymentViaFactory('approved', ['code' => '001001']);
    $this->createPaymentViaFactory('approved', ['code' => '001002']);

    $response = $this->getJson(route('customers.payments.index', ['search' => '001001']))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.code'))->toBe('001001');
});

it('searches by rtc_book_number', function () {
    $this->createPaymentViaFactory('approved', ['rtc_book_number' => 'RTC-SEARCH-123']);
    $this->createPaymentViaFactory('approved', ['rtc_book_number' => 'RTC-OTHER-456']);

    $response = $this->getJson(route('customers.payments.index', ['search' => 'SEARCH-123']))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.rtc_book_number'))->toBe('RTC-SEARCH-123');
});

it('paginates results', function () {
    for ($i = 0; $i < 7; $i++) {
        $this->createPaymentViaFactory('approved');
    }

    $response = $this->getJson(route('customers.payments.index', ['per_page' => 3]))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(3)
        ->and($response->json('pagination.total'))->toBe(7)
        ->and($response->json('pagination.last_page'))->toBe(3);
});
