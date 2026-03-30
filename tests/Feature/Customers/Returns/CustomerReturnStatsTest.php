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

it('returns correct return statistics', function () {
    for ($i = 0; $i < 3; $i++) {
        $this->createPendingReturn();
    }
    for ($i = 0; $i < 2; $i++) {
        $this->createApprovedReturn();
    }
    $this->createApprovedReturn([
        'return_received_by' => $this->admin->id,
        'return_received_at' => now(),
    ]);
    $trashed = $this->createApprovedReturn();
    $trashed->delete();

    $stats = $this->getJson(route('customers.returns.stats'))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'total_returns',
                'received_returns',
                'not_received_returns',
                'trashed_returns',
                'total_amount',
                'total_amount_usd',
                'returns_by_prefix',
                'returns_by_warehouse',
                'returns_by_currency',
                'recent_received',
            ],
        ])
        ->json('data');

    expect($stats['total_returns'])->toBe(3)
        ->and($stats['received_returns'])->toBe(1)
        ->and($stats['not_received_returns'])->toBe(2)
        ->and($stats['trashed_returns'])->toBe(1);
});

it('calculates totals from approved returns only', function () {
    for ($i = 0; $i < 2; $i++) {
        $this->createPendingReturn(['total' => 1000.00, 'total_usd' => 800.00]);
    }
    for ($i = 0; $i < 3; $i++) {
        $this->createApprovedReturn(['total' => 500.00, 'total_usd' => 400.00]);
    }

    $stats = $this->getJson(route('customers.returns.stats'))
        ->assertOk()
        ->json('data');

    expect((float) $stats['total_amount'])->toBe(1500.00)    // 3 × 500
        ->and((float) $stats['total_amount_usd'])->toBe(1200.00) // 3 × 400
        ->and($stats['total_returns'])->toBe(3);
});

it('salesman sees only their own statistics', function () {
    $otherSalesman = User::factory()->create(['role' => User::ROLE_SALESMAN]);
    Employee::factory()->create([
        'id'        => $otherSalesman->id,
        'user_id'   => $otherSalesman->id,
        'is_active' => true,
    ]);
    $otherCustomer = Customer::factory()->create(['salesperson_id' => $otherSalesman->id]);

    for ($i = 0; $i < 2; $i++) {
        $this->createApprovedReturn(['total' => 500.00]);
    }
    for ($i = 0; $i < 3; $i++) {
        $this->createApprovedReturn([
            'customer_id'    => $otherCustomer->id,
            'salesperson_id' => $otherSalesman->id,
            'total'          => 1000.00,
        ]);
    }

    $stats = $this->getJson(route('customers.returns.stats'))
        ->assertOk()
        ->json('data');

    expect($stats['total_returns'])->toBe(2)
        ->and((float) $stats['total_amount'])->toBe(1000.00); // 2 × 500
});
