<?php

use App\Models\Customers\Customer;
use App\Models\Employees\Employee;
use Tests\Feature\Customers\ReturnOrders\Concerns\HasCustomerReturnOrderSetup;

uses(HasCustomerReturnOrderSetup::class);

beforeEach(function () {
    $this->setUpCustomerReturnOrders();
    $this->actingAs($this->salesman, 'sanctum');
});

it('lists pending return orders with correct structure', function () {
    for ($i = 0; $i < 2; $i++) {
        $this->createPendingReturn();
    }
    for ($i = 0; $i < 3; $i++) {
        $this->createApprovedReturn();
    }

    $response = $this->getJson(route('customers.return-orders.index'))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => ['*' => ['id', 'code', 'return_code', 'date', 'prefix', 'total', 'total_usd', 'status', 'is_approved', 'is_pending', 'customer', 'currency', 'warehouse', 'salesperson']],
            'pagination',
        ]);

    expect($response->json('data'))->toHaveCount(2);
    foreach ($response->json('data') as $return) {
        expect($return['is_pending'])->toBe(true);
    }
});

it('salesman only sees their own return orders', function () {
    $otherSalesman = User::factory()->create(['role' => User::ROLE_SALESMAN]);
    Employee::factory()->create([
        'id'        => $otherSalesman->id,
        'user_id'   => $otherSalesman->id,
        'is_active' => true,
    ]);
    $otherCustomer = Customer::factory()->create(['salesperson_id' => $otherSalesman->id]);

    for ($i = 0; $i < 2; $i++) {
        $this->createPendingReturn();
    }
    for ($i = 0; $i < 3; $i++) {
        $this->createPendingReturn([
            'customer_id'    => $otherCustomer->id,
            'salesperson_id' => $otherSalesman->id,
        ]);
    }

    $response = $this->getJson(route('customers.return-orders.index'))->assertOk();

    expect($response->json('data'))->toHaveCount(2);
    foreach ($response->json('data') as $return) {
        expect($return['salesperson']['id'])->toBe($this->salesman->id);
    }
});

it('filters by customer', function () {
    $otherCustomer = Customer::factory()->create([
        'salesperson_id' => $this->salesman->id,
        'is_active'      => true,
    ]);

    for ($i = 0; $i < 2; $i++) {
        $this->createPendingReturn(['customer_id' => $this->customer->id]);
    }
    for ($i = 0; $i < 3; $i++) {
        $this->createPendingReturn(['customer_id' => $otherCustomer->id]);
    }

    $this->getJson(route('customers.return-orders.index', ['customer_id' => $this->customer->id]))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('searches by code', function () {
    $this->createPendingReturn(['code' => '001001']);
    $this->createPendingReturn(['code' => '001002']);

    $response = $this->getJson(route('customers.return-orders.index', ['search' => '001001']))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.code'))->toBe('001001');
});
