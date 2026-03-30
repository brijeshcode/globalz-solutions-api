<?php

use App\Models\Customers\Customer;
use Tests\Feature\Customers\Customers\Concerns\HasCustomerSetup;

uses(HasCustomerSetup::class);

beforeEach(function () {
    $this->setUpCustomers();
});

it('can update a customer', function () {
    $customer     = Customer::factory()->create([
        'customer_type_id'  => $this->customerType->id,
        'customer_group_id' => $this->customerGroup->id,
    ]);
    $originalCode = $customer->code;

    $this->putJson(route('customers.update', $customer), $this->customerPayload([
        'name'  => 'Updated Customer',
        'email' => 'updated@example.com',
        'notes' => 'Updated notes',
    ]))->assertOk()
       ->assertJson(['data' => ['code' => $originalCode, 'name' => 'Updated Customer', 'email' => 'updated@example.com']]);

    $this->assertDatabaseHas('customers', [
        'id'    => $customer->id,
        'code'  => $originalCode,
        'name'  => 'Updated Customer',
        'email' => 'updated@example.com',
    ]);
});

it('code cannot be updated once set', function () {
    $customer     = Customer::factory()->create();
    $originalCode = $customer->code;

    $this->putJson(route('customers.update', $customer), $this->customerPayload([
        'name' => 'Updated Customer',
        'code' => '99999999',
    ]))->assertOk();

    expect($customer->fresh()->code)->toBe($originalCode)
        ->and($customer->fresh()->code)->not()->toBe('99999999');
});

it('validates parent_id cannot be self', function () {
    $customer = Customer::factory()->create();

    $this->putJson(route('customers.update', $customer), $this->customerPayload([
        'name'      => 'Updated Customer',
        'parent_id' => $customer->id,
    ]))->assertUnprocessable()
       ->assertJsonValidationErrors(['parent_id']);
});

it('validates circular parent reference is rejected', function () {
    $parent = Customer::factory()->create();
    $child  = Customer::factory()->create(['parent_id' => $parent->id]);

    $this->putJson(route('customers.update', $parent), $this->customerPayload([
        'name'      => 'Updated Parent',
        'parent_id' => $child->id,
    ]))->assertUnprocessable()
       ->assertJsonValidationErrors(['parent_id']);
});

it('validates cannot deactivate a customer with active children', function () {
    $parent = Customer::factory()->create(['is_active' => true]);
    Customer::factory()->create(['parent_id' => $parent->id, 'is_active' => true]);

    $this->putJson(route('customers.update', $parent), $this->customerPayload([
        'name'      => $parent->name,
        'is_active' => false,
    ]))->assertUnprocessable()
       ->assertJsonValidationErrors(['is_active']);
});
