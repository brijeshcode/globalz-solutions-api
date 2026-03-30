<?php

use App\Models\Customers\Customer;
use App\Models\Employees\Employee;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\Warehouse;
use App\Models\User;
use Tests\Feature\Customers\Returns\Concerns\HasCustomerReturnSetup;

uses(HasCustomerReturnSetup::class);

beforeEach(function () {
    $this->setUpCustomerReturns();
    $this->actingAs($this->salesman, 'sanctum');
});

it('lists approved returns with correct structure', function () {
    for ($i = 0; $i < 2; $i++) {
        $this->createPendingReturn();
    }
    for ($i = 0; $i < 3; $i++) {
        $this->createApprovedReturn();
    }

    $response = $this->getJson(route('customers.returns.index'))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => ['*' => ['id', 'code', 'return_code', 'date', 'prefix', 'total', 'total_usd', 'status', 'is_approved', 'is_pending', 'customer', 'currency', 'warehouse', 'salesperson', 'approved_by_user']],
            'pagination',
        ]);

    expect($response->json('data'))->toHaveCount(3);
    foreach ($response->json('data') as $return) {
        expect($return['is_approved'])->toBe(true);
    }
});

it('salesman only sees their own approved returns', function () {
    $otherSalesman = User::factory()->create(['role' => User::ROLE_SALESMAN]);
    Employee::factory()->create([
        'id'        => $otherSalesman->id,
        'user_id'   => $otherSalesman->id,
        'is_active' => true,
    ]);
    $otherCustomer = Customer::factory()->create(['salesperson_id' => $otherSalesman->id]);

    for ($i = 0; $i < 2; $i++) {
        $this->createApprovedReturn();
    }
    for ($i = 0; $i < 3; $i++) {
        $this->createApprovedReturn([
            'customer_id'    => $otherCustomer->id,
            'salesperson_id' => $otherSalesman->id,
        ]);
    }

    $response = $this->getJson(route('customers.returns.index'))->assertOk();

    expect($response->json('data'))->toHaveCount(2);
    foreach ($response->json('data') as $return) {
        expect($return['salesperson']['id'])->toBe($this->salesman->id);
    }
});

it('filters by customer', function () {
    $other = Customer::factory()->create([
        'salesperson_id' => $this->salesman->id,
        'is_active'      => true,
    ]);

    for ($i = 0; $i < 2; $i++) {
        $this->createApprovedReturn(['customer_id' => $this->customer->id]);
    }
    for ($i = 0; $i < 3; $i++) {
        $this->createApprovedReturn(['customer_id' => $other->id]);
    }

    $this->getJson(route('customers.returns.index', ['customer_id' => $this->customer->id]))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('filters by currency', function () {
    $other = Currency::factory()->eur()->create(['is_active' => true]);

    for ($i = 0; $i < 2; $i++) {
        $this->createApprovedReturn(['currency_id' => $this->currency->id]);
    }
    for ($i = 0; $i < 3; $i++) {
        $this->createApprovedReturn(['currency_id' => $other->id]);
    }

    $this->getJson(route('customers.returns.index', ['currency_id' => $this->currency->id]))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('filters by warehouse', function () {
    $other = Warehouse::factory()->create(['is_active' => true]);

    for ($i = 0; $i < 2; $i++) {
        $this->createApprovedReturn(['warehouse_id' => $this->warehouse->id]);
    }
    for ($i = 0; $i < 3; $i++) {
        $this->createApprovedReturn(['warehouse_id' => $other->id]);
    }

    $this->getJson(route('customers.returns.index', ['warehouse_id' => $this->warehouse->id]))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('filters by date range', function () {
    $this->createApprovedReturn(['date' => '2025-01-01']);
    $this->createApprovedReturn(['date' => '2025-02-15']);
    $this->createApprovedReturn(['date' => '2025-03-30']);

    $this->getJson(route('customers.returns.index', ['start_date' => '2025-02-01', 'end_date' => '2025-02-28']))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters by prefix', function () {
    for ($i = 0; $i < 2; $i++) {
        $this->createApprovedReturn(['prefix' => 'RTX']);
    }
    for ($i = 0; $i < 3; $i++) {
        $this->createApprovedReturn(['prefix' => 'RTV']);
    }

    $this->getJson(route('customers.returns.index', ['prefix' => 'RTX']))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('filters by received status', function () {
    for ($i = 0; $i < 2; $i++) {
        $this->createApprovedReturn([
            'return_received_by' => $this->admin->id,
            'return_received_at' => now(),
        ]);
    }
    for ($i = 0; $i < 3; $i++) {
        $this->createApprovedReturn([
            'return_received_by' => null,
            'return_received_at' => null,
        ]);
    }

    $this->getJson(route('customers.returns.index', ['status' => 'received']))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->getJson(route('customers.returns.index', ['status' => 'not_received']))
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('searches by code', function () {
    $this->createApprovedReturn(['code' => '001001']);
    $this->createApprovedReturn(['code' => '001002']);

    $response = $this->getJson(route('customers.returns.index', ['search' => '001001']))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.code'))->toBe('001001');
});

it('paginates results', function () {
    for ($i = 0; $i < 7; $i++) {
        $this->createApprovedReturn();
    }

    $response = $this->getJson(route('customers.returns.index', ['per_page' => 3]))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(3)
        ->and($response->json('pagination.total'))->toBe(7)
        ->and($response->json('pagination.last_page'))->toBe(3);
});
