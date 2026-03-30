<?php

use App\Models\Customers\Customer;
use App\Models\Customers\CustomerReturn;
use App\Models\Employees\Employee;
use App\Models\Items\Item;
use App\Models\Setups\Warehouse;
use App\Models\User;
use Tests\Feature\Customers\Returns\Concerns\HasCustomerReturnSetup;

uses(HasCustomerReturnSetup::class);

beforeEach(function () {
    $this->setUpCustomerReturns();
    $this->actingAs($this->admin, 'sanctum');
});

it('admin creates an approved return', function () {
    $this->postJson(route('customers.returns.store'), $this->returnPayload([
        'approve_note' => 'Admin approved during creation',
    ]))->assertCreated()
      ->assertJson(['data' => ['status' => 'approved', 'is_approved' => true, 'is_pending' => false]]);

    $return = CustomerReturn::latest()->first();
    expect($return->isApproved())->toBeTrue()
        ->and($return->approved_by)->toBe($this->admin->id)
        ->and($return->approve_note)->toBe('Admin approved during creation');
});

it('non-admin cannot create returns', function () {
    $this->actingAs($this->salesman, 'sanctum');

    $this->postJson(route('customers.returns.store'), $this->returnPayload())
        ->assertForbidden();
});

it('sets created_by and updated_by to the authenticated user', function () {
    $return = $this->createReturnViaApi();

    expect($return->created_by)->toBe($this->admin->id)
        ->and($return->updated_by)->toBe($this->admin->id);
});

it('requires all mandatory fields', function () {
    $this->postJson(route('customers.returns.store'), [
        'customer_id' => null,
        'currency_id' => null,
        'total'       => -100,
        'items'       => [],
    ])->assertUnprocessable()
      ->assertJsonValidationErrors(['date', 'prefix', 'customer_id', 'currency_id', 'warehouse_id', 'total', 'total_usd', 'items']);
});

it('rejects an invalid prefix', function () {
    $this->postJson(route('customers.returns.store'), $this->returnPayload(['prefix' => 'INVALID']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['prefix']);
});

it('rejects an inactive customer', function () {
    $inactive = Customer::factory()->create([
        'is_active'      => false,
        'salesperson_id' => $this->salesman->id,
    ]);

    $this->postJson(route('customers.returns.store'), $this->returnPayload(['customer_id' => $inactive->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['customer_id']);
});

it('rejects a customer not matching the specified salesperson', function () {
    $otherSalesman = User::factory()->create(['role' => User::ROLE_SALESMAN]);
    Employee::factory()->create([
        'id'        => $otherSalesman->id,
        'user_id'   => $otherSalesman->id,
        'is_active' => true,
    ]);
    $otherCustomer = Customer::factory()->create([
        'salesperson_id' => $otherSalesman->id,
        'is_active'      => true,
    ]);

    $this->postJson(route('customers.returns.store'), $this->returnPayload([
        'customer_id'    => $otherCustomer->id,
        'salesperson_id' => $this->salesman->id, // mismatched salesperson
    ]))->assertUnprocessable()
       ->assertJsonValidationErrors(['customer_id']);
});

it('rejects an inactive warehouse', function () {
    $inactive = Warehouse::factory()->create(['is_active' => false]);

    $this->postJson(route('customers.returns.store'), $this->returnPayload(['warehouse_id' => $inactive->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['warehouse_id']);
});

it('rejects inactive items', function () {
    $inactive = Item::factory()->create(['is_active' => false]);

    $this->postJson(route('customers.returns.store'), $this->returnPayload([
        'items' => [
            [
                'item_code' => 'INACTIVE001',
                'item_id'   => $inactive->id,
                'quantity'  => 10,
                'price'     => 100.00,
            ],
        ],
    ]))->assertUnprocessable()
       ->assertJsonValidationErrors(['items.0.item_id']);
});
