<?php

use App\Models\Customers\Customer;
use Tests\Feature\Customers\Customers\Concerns\HasCustomerSetup;

uses(HasCustomerSetup::class);

beforeEach(function () {
    $this->setUpCustomers();
});

it('returns correct customer statistics', function () {
    Customer::factory()->count(5)->create(['is_active' => true]);
    Customer::factory()->count(2)->create(['is_active' => false]);

    $stats = $this->getJson(route('customers.stats'))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => ['total_customers', 'total_customer_balance'],
        ])
        ->json('data');

    expect($stats['total_customers'])->toBe(7);
});
