<?php

use App\Models\Customers\Customer;
use App\Models\Employees\Employee;
use App\Models\User;
use Tests\Feature\Customers\Returns\Concerns\HasCustomerReturnSetup;

uses(HasCustomerReturnSetup::class);

beforeEach(function () {
    $this->setUpCustomerReturns();
    $this->actingAs($this->salesman, 'sanctum');
});

it('shows a return with all relationships loaded', function () {
    $return = $this->createReturnViaApi();

    $this->getJson(route('customers.returns.show', $return))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'id',
                'return_code',
                'customer'        => ['id', 'name', 'code', 'address', 'city', 'mobile', 'mof_tax_number'],
                'currency'        => ['id', 'name', 'code', 'symbol'],
                'warehouse'       => ['id', 'name', 'address'],
                'salesperson'     => ['id', 'name'],
                'approved_by_user' => ['id', 'name'],
                'created_by_user' => ['id', 'name'],
                'updated_by_user' => ['id', 'name'],
                'items'           => ['*' => ['item' => ['id', 'description', 'code']]],
            ],
        ]);
});

it('cannot show a non-approved return', function () {
    $return = $this->createPendingReturn();

    $this->getJson(route('customers.returns.show', $return))
        ->assertNotFound()
        ->assertJson(['message' => 'Return is not approved']);
});

it('salesman can only view their own approved return', function () {
    $otherSalesman = User::factory()->create(['role' => User::ROLE_SALESMAN]);
    Employee::factory()->create([
        'id'        => $otherSalesman->id,
        'user_id'   => $otherSalesman->id,
        'is_active' => true,
    ]);
    $otherCustomer = Customer::factory()->create(['salesperson_id' => $otherSalesman->id]);

    $return = $this->createApprovedReturn([
        'customer_id'    => $otherCustomer->id,
        'salesperson_id' => $otherSalesman->id,
    ]);

    $this->getJson(route('customers.returns.show', $return))
        ->assertForbidden()
        ->assertJson(['message' => 'You can only view your own return']);
});

it('returns 404 for a non-existent return', function () {
    $this->getJson(route('customers.returns.show', 999999))
        ->assertNotFound();
});
