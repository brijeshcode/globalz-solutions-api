<?php

use App\Models\Customers\Customer;
use App\Models\Customers\CustomerReturn;
use App\Models\Employees\Employee;
use App\Models\Items\Item;
use App\Models\Setups\Warehouse;
use App\Models\User;
use Tests\Feature\Customers\ReturnOrders\Concerns\HasCustomerReturnOrderSetup;

uses(HasCustomerReturnOrderSetup::class);

beforeEach(function () {
    $this->setUpCustomerReturnOrders();
    $this->actingAs($this->salesman, 'sanctum');
});

it('salesman creates a pending return order', function () {
    $this->postJson(route('customers.return-orders.store'), $this->returnPayload())
        ->assertCreated()
        ->assertJson(['data' => ['status' => 'pending', 'is_pending' => true, 'is_approved' => false]]);

    $return = CustomerReturn::latest()->first();
    expect($return->isPending())->toBeTrue()
        ->and($return->approved_by)->toBeNull()
        ->and($return->salesperson_id)->toBe($this->salesman->id);
});

it('sets created_by and updated_by to the authenticated user', function () {
    $this->postJson(route('customers.return-orders.store'), $this->returnPayload())
        ->assertCreated();

    $return = CustomerReturn::latest()->first();
    expect($return->created_by)->toBe($this->salesman->id)
        ->and($return->updated_by)->toBe($this->salesman->id);
});

it('requires all mandatory fields', function () {
    $this->postJson(route('customers.return-orders.store'), [
        'customer_id' => null,
        'currency_id' => null,
        'total'       => -100,
        'items'       => [],
    ])->assertUnprocessable()
      ->assertJsonValidationErrors(['date', 'prefix', 'customer_id', 'currency_id', 'warehouse_id', 'total', 'total_usd', 'items']);
});

it('rejects an invalid prefix', function () {
    $this->postJson(route('customers.return-orders.store'), $this->returnPayload(['prefix' => 'INVALID']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['prefix']);
});

it('rejects a customer not belonging to the salesperson', function () {
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

    $this->postJson(route('customers.return-orders.store'), $this->returnPayload(['customer_id' => $otherCustomer->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['customer_id']);
});

it('rejects an inactive customer', function () {
    $inactive = Customer::factory()->create([
        'is_active'      => false,
        'salesperson_id' => $this->salesman->id,
    ]);

    $this->postJson(route('customers.return-orders.store'), $this->returnPayload(['customer_id' => $inactive->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['customer_id']);
});

it('rejects an inactive warehouse', function () {
    $inactive = Warehouse::factory()->create(['is_active' => false]);

    $this->postJson(route('customers.return-orders.store'), $this->returnPayload(['warehouse_id' => $inactive->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['warehouse_id']);
});

it('rejects inactive items', function () {
    $inactive = Item::factory()->create([
        'is_active'  => false,
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $this->postJson(route('customers.return-orders.store'), $this->returnPayload([
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
