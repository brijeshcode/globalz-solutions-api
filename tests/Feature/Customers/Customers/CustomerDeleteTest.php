<?php

use App\Models\Customers\Customer;
use Tests\Feature\Customers\Customers\Concerns\HasCustomerSetup;

uses(HasCustomerSetup::class);

beforeEach(function () {
    $this->setUpCustomers();
});

it('can soft delete a customer', function () {
    $customer = Customer::factory()->create(['customer_type_id' => $this->customerType->id]);

    $this->deleteJson(route('customers.destroy', $customer))
        ->assertStatus(204);

    $this->assertSoftDeleted('customers', ['id' => $customer->id]);
});

it('cannot delete a customer with child customers', function () {
    $parent = Customer::factory()->create();
    Customer::factory()->create(['parent_id' => $parent->id]);

    $this->deleteJson(route('customers.destroy', $parent))
        ->assertUnprocessable()
        ->assertJson(['message' => 'Cannot delete customer with child customers. Please handle child customers first.']);

    $this->assertDatabaseHas('customers', ['id' => $parent->id, 'deleted_at' => null]);
});

it('lists trashed customers', function () {
    $customer = Customer::factory()->create(['customer_type_id' => $this->customerType->id]);
    $customer->delete();

    $this->getJson(route('customers.trashed'))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => ['*' => ['id', 'code', 'name', 'customer_type', 'is_active']],
            'pagination',
        ])
        ->assertJsonCount(1, 'data');
});

it('can restore a trashed customer', function () {
    $customer = Customer::factory()->create();
    $customer->delete();

    $this->patchJson(route('customers.restore', $customer->id))
        ->assertOk();

    $this->assertDatabaseHas('customers', ['id' => $customer->id, 'deleted_at' => null]);
});

it('can permanently delete a trashed customer', function () {
    $customer = Customer::factory()->create();
    $customer->delete();

    $this->deleteJson(route('customers.force-delete', $customer->id))
        ->assertStatus(204);

    $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
});
