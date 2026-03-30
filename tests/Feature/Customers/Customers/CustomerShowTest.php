<?php

use App\Models\Customers\Customer;
use Tests\Feature\Customers\Customers\Concerns\HasCustomerSetup;

uses(HasCustomerSetup::class);

beforeEach(function () {
    $this->setUpCustomers();
});

it('shows a customer', function () {
    $customer = Customer::factory()->create([
        'customer_type_id'  => $this->customerType->id,
        'customer_group_id' => $this->customerGroup->id,
        'salesperson_id'    => $this->salesperson->id,
    ]);

    $this->getJson(route('customers.show', $customer))
        ->assertOk()
        ->assertJson(['data' => ['id' => $customer->id, 'code' => $customer->code, 'name' => $customer->name]]);
});

it('returns 404 for a non-existent customer', function () {
    $this->getJson(route('customers.show', 999999))
        ->assertNotFound();
});
