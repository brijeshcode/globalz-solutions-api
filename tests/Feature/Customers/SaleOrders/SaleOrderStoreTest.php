<?php

use App\Models\Customers\Customer;
use App\Models\Customers\Sale;
use App\Models\Employees\Employee;
use App\Models\Items\Item;
use App\Models\Setups\Warehouse;
use App\Models\User;
use Tests\Feature\Customers\SaleOrders\Concerns\HasSaleOrderSetup;

uses(HasSaleOrderSetup::class);

beforeEach(function () {
    $this->setUpSaleOrders();
    $this->actingAs($this->salesman, 'sanctum');
});

it('salesman creates a pending sale order', function () {
    $this->postJson(route('customers.sale-orders.store'), $this->saleOrderPayload())
        ->assertCreated()
        ->assertJson(['data' => ['is_pending' => true, 'is_approved' => false]]);

    $sale = Sale::latest()->first();
    expect($sale->isPending())->toBeTrue()
        ->and($sale->approved_by)->toBeNull()
        ->and($sale->salesperson_id)->toBe($this->salesmanEmployee->id);
});

it('calculates profit correctly on creation', function () {
    $this->postJson(route('customers.sale-orders.store'), $this->saleOrderPayload([
        'items' => [
            [
                'item_id'              => $this->item->id,
                'quantity'             => 10,
                'price'                => 100.00,
                'unit_discount_amount' => 5.00,
                'ttc_price'            => 104.025,
                'tax_percent'          => 10.0,
                'discount_percent'     => 5.0,
                'discount_amount'      => 50.00,
                'total_price'          => 950.00,
                'total_price_usd'      => 950.00,
            ],
        ],
    ]))->assertCreated();

    // Profit = (sale price - discount) - cost = (100 - 5) - 50 = 45/unit × 10 = 450
    expect((float) Sale::latest()->first()->total_profit)->toBe(450.0);
});

it('sets created_by and updated_by to the authenticated user', function () {
    $this->postJson(route('customers.sale-orders.store'), $this->saleOrderPayload())->assertCreated();

    $sale = Sale::latest()->first();
    expect($sale->created_by)->toBe($this->salesman->id)
        ->and($sale->updated_by)->toBe($this->salesman->id);
});

it('requires all mandatory fields', function () {
    $this->postJson(route('customers.sale-orders.store'), [
        'customer_id' => null,
        'currency_id' => null,
        'total'       => -100,
        'items'       => [],
    ])->assertUnprocessable()
      ->assertJsonValidationErrors(['date', 'prefix', 'customer_id', 'currency_id', 'warehouse_id', 'total', 'total_usd', 'items']);
});

it('rejects an invalid prefix', function () {
    $this->postJson(route('customers.sale-orders.store'), $this->saleOrderPayload(['prefix' => 'INVALID']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['prefix']);
});

it('rejects a customer not belonging to the salesperson', function () {
    $otherSalesman = User::factory()->create(['role' => User::ROLE_SALESMAN]);
    $otherEmployee = Employee::factory()->create([
        'id'        => $otherSalesman->id,
        'user_id'   => $otherSalesman->id,
        'is_active' => true,
    ]);
    $otherCustomer = Customer::factory()->create([
        'salesperson_id' => $otherEmployee->id,
        'is_active'      => true,
    ]);

    $this->postJson(route('customers.sale-orders.store'), $this->saleOrderPayload(['customer_id' => $otherCustomer->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['customer_id']);
});

it('rejects an inactive customer', function () {
    $inactive = Customer::factory()->create([
        'is_active'      => false,
        'salesperson_id' => $this->salesmanEmployee->id,
    ]);

    $this->postJson(route('customers.sale-orders.store'), $this->saleOrderPayload(['customer_id' => $inactive->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['customer_id']);
});

it('rejects an inactive warehouse', function () {
    $inactive = Warehouse::factory()->create(['is_active' => false]);

    $this->postJson(route('customers.sale-orders.store'), $this->saleOrderPayload(['warehouse_id' => $inactive->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['warehouse_id']);
});

it('rejects inactive items', function () {
    $inactive = Item::factory()->create([
        'is_active'  => false,
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $this->postJson(route('customers.sale-orders.store'), $this->saleOrderPayload([
        'items' => [['item_id' => $inactive->id, 'quantity' => 10, 'price' => 100.00, 'total_price' => 1000.00]],
    ]))->assertUnprocessable()
       ->assertJsonValidationErrors(['items.0.item_id']);
});
