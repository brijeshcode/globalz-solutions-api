<?php

use App\Models\Customers\Customer;
use App\Models\Employees\Employee;
use App\Models\User;
use Tests\Feature\Customers\SaleOrders\Concerns\HasSaleOrderSetup;

uses(HasSaleOrderSetup::class);

beforeEach(function () {
    $this->setUpSaleOrders();
    $this->actingAs($this->salesman, 'sanctum');
});

it('lists pending sale orders with correct structure', function () {
    for ($i = 0; $i < 2; $i++) {
        $this->createPendingSaleOrder();
    }
    for ($i = 0; $i < 3; $i++) {
        $this->createApprovedSaleOrder();
    }

    $response = $this->getJson(route('customers.sale-orders.index'))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => ['*' => ['id', 'code', 'sale_code', 'date', 'prefix', 'total', 'total_usd', 'total_profit', 'status', 'is_approved', 'is_pending', 'customer', 'currency', 'warehouse', 'salesperson']],
            'pagination',
        ]);

    expect($response->json('data'))->toHaveCount(2);
    foreach ($response->json('data') as $sale) {
        expect($sale['is_pending'])->toBe(true);
    }
});

it('salesman only sees their own sale orders', function () {
    $otherSalesman = User::factory()->create(['role' => User::ROLE_SALESMAN]);
    $otherEmployee = Employee::factory()->create([
        'id'        => $otherSalesman->id,
        'user_id'   => $otherSalesman->id,
        'is_active' => true,
    ]);
    $otherCustomer = Customer::factory()->create(['salesperson_id' => $otherEmployee->id]);

    for ($i = 0; $i < 2; $i++) {
        $this->createPendingSaleOrder();
    }
    for ($i = 0; $i < 3; $i++) {
        $this->createPendingSaleOrder([
            'customer_id'    => $otherCustomer->id,
            'salesperson_id' => $otherEmployee->id,
        ]);
    }

    $response = $this->getJson(route('customers.sale-orders.index'))->assertOk();

    expect($response->json('data'))->toHaveCount(2);
    foreach ($response->json('data') as $sale) {
        expect($sale['salesperson']['id'])->toBe($this->salesmanEmployee->id);
    }
});

it('filters by customer', function () {
    $other = Customer::factory()->create([
        'salesperson_id' => $this->salesmanEmployee->id,
        'is_active'      => true,
    ]);

    for ($i = 0; $i < 2; $i++) {
        $this->createPendingSaleOrder(['customer_id' => $this->customer->id]);
    }
    for ($i = 0; $i < 3; $i++) {
        $this->createPendingSaleOrder(['customer_id' => $other->id]);
    }

    $this->getJson(route('customers.sale-orders.index', ['customer_id' => $this->customer->id]))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('searches by code', function () {
    $this->createPendingSaleOrder(['code' => '001001']);
    $this->createPendingSaleOrder(['code' => '001002']);

    $response = $this->getJson(route('customers.sale-orders.index', ['search' => '001001']))->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.code'))->toBe('001001');
});
