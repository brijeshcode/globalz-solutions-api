<?php

use App\Models\Customers\Customer;
use App\Models\Setups\Generals\Currencies\Currency;
use Tests\Feature\Customers\PaymentOrders\Concerns\HasCustomerPaymentOrderSetup;

uses(HasCustomerPaymentOrderSetup::class);

beforeEach(function () {
    $this->setUpCustomerPaymentOrders();
    $this->actingAs($this->salesman, 'sanctum');
});

it('lists payment orders with correct structure', function () {
    for ($i = 0; $i < 3; $i++) {
        $this->createOrderViaFactory();
    }

    $this->getJson(route('customers.payment-orders.index'))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => ['*' => ['id', 'code', 'payment_code', 'date', 'prefix', 'amount', 'amount_usd', 'status', 'is_approved', 'is_pending', 'customer', 'currency']],
            'pagination',
        ])
        ->assertJsonCount(3, 'data');
});

it('filters by customer', function () {
    $other = Customer::factory()->create(['created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);

    for ($i = 0; $i < 2; $i++) {
        $this->createOrderViaFactory(null, ['customer_id' => $this->customer->id]);
    }
    for ($i = 0; $i < 3; $i++) {
        $this->createOrderViaFactory(null, ['customer_id' => $other->id]);
    }

    $this->getJson(route('customers.payment-orders.index', ['customer_id' => $this->customer->id]))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('filters by currency', function () {
    $other = Currency::factory()->eur()->create(['is_active' => true]);

    for ($i = 0; $i < 2; $i++) {
        $this->createOrderViaFactory(null, ['currency_id' => $this->currency->id]);
    }
    for ($i = 0; $i < 3; $i++) {
        $this->createOrderViaFactory(null, ['currency_id' => $other->id]);
    }

    $this->getJson(route('customers.payment-orders.index', ['currency_id' => $this->currency->id]))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('filters by date range', function () {
    $this->createOrderViaFactory(null, ['date' => '2025-01-01']);
    $this->createOrderViaFactory(null, ['date' => '2025-02-15']);
    $this->createOrderViaFactory(null, ['date' => '2025-03-30']);

    $this->getJson(route('customers.payment-orders.index', ['start_date' => '2025-02-01', 'end_date' => '2025-02-28']))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('searches by payment code', function () {
    $this->createOrderViaFactory(null, ['code' => '001001']);
    $this->createOrderViaFactory(null, ['code' => '001002']);

    $response = $this->getJson(route('customers.payment-orders.index', ['search' => '001001']))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.code'))->toBe('001001');
});

it('searches by rtc_book_number', function () {
    $this->createOrderViaFactory(null, ['rtc_book_number' => 'RTC-SEARCH-123']);
    $this->createOrderViaFactory(null, ['rtc_book_number' => 'RTC-OTHER-456']);

    $response = $this->getJson(route('customers.payment-orders.index', ['search' => 'SEARCH-123']))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.rtc_book_number'))->toBe('RTC-SEARCH-123');
});

it('paginates results', function () {
    for ($i = 0; $i < 7; $i++) {
        $this->createOrderViaFactory();
    }

    $response = $this->getJson(route('customers.payment-orders.index', ['per_page' => 3]))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(3)
        ->and($response->json('pagination.total'))->toBe(7)
        ->and($response->json('pagination.last_page'))->toBe(3);
});
