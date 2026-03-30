<?php

use App\Models\Customers\Customer;
use App\Models\Employees\Employee;
use App\Models\Setups\Customers\CustomerGroup;
use App\Models\Setups\Customers\CustomerType;
use Tests\Feature\Customers\Customers\Concerns\HasCustomerSetup;

uses(HasCustomerSetup::class);

beforeEach(function () {
    $this->setUpCustomers();
});

it('lists customers with correct structure', function () {
    Customer::factory()->count(3)->create([
        'customer_type_id'  => $this->customerType->id,
        'customer_group_id' => $this->customerGroup->id,
    ]);

    $this->getJson(route('customers.index'))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => ['*' => ['id', 'code', 'name', 'current_balance', 'is_active']],
            'pagination',
        ])
        ->assertJsonCount(3, 'data');
});

it('searches by name', function () {
    Customer::factory()->create(['name' => 'Searchable Customer']);
    Customer::factory()->create(['name' => 'Another Customer']);

    $response = $this->getJson(route('customers.index', ['search' => 'Searchable']))->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.name'))->toBe('Searchable Customer');
});

it('searches by code', function () {
    $customer1 = Customer::factory()->create(['name' => 'First Customer']);
    Customer::factory()->create(['name' => 'Second Customer']);

    $response = $this->getJson(route('customers.index', ['search' => $customer1->code]))->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.code'))->toBe($customer1->code);
});

it('filters by active status', function () {
    Customer::factory()->create(['is_active' => true]);
    Customer::factory()->create(['is_active' => false]);

    $response = $this->getJson(route('customers.index', ['is_active' => true]))->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.is_active'))->toBe(true);
});

it('filters by customer type', function () {
    $retail    = CustomerType::factory()->create(['name' => 'Retail']);
    $wholesale = CustomerType::factory()->create(['name' => 'Wholesale']);

    Customer::factory()->create(['customer_type_id' => $retail->id]);
    Customer::factory()->create(['customer_type_id' => $wholesale->id]);

    $this->getJson(route('customers.index', ['customer_type_id' => $retail->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters by customer group', function () {
    $vip     = CustomerGroup::factory()->create(['name' => 'VIP']);
    $regular = CustomerGroup::factory()->create(['name' => 'Regular']);

    Customer::factory()->create(['customer_group_id' => $vip->id]);
    Customer::factory()->create(['customer_group_id' => $regular->id]);

    $this->getJson(route('customers.index', ['customer_group_id' => $vip->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters by salesperson', function () {
    $other = Employee::factory()->create(['department_id' => $this->salesDepartment->id]);

    Customer::factory()->create(['salesperson_id' => $this->salesperson->id]);
    Customer::factory()->create(['salesperson_id' => $other->id]);

    $response = $this->getJson(route('customers.index', ['salesperson_id' => $this->salesperson->id]))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.salesperson.id'))->toBe($this->salesperson->id);
});

it('filters by balance range', function () {
    Customer::factory()->create(['current_balance' => 1000]);
    Customer::factory()->create(['current_balance' => 5000]);
    Customer::factory()->create(['current_balance' => 10000]);

    $response = $this->getJson(route('customers.index', ['min_balance' => 2000, 'max_balance' => 8000]))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.current_balance'))->toEqual(5000);
});

it('filters customers over credit limit', function () {
    Customer::factory()->create(['current_balance' => 5000,  'credit_limit' => 10000]);
    Customer::factory()->create(['current_balance' => 15000, 'credit_limit' => 10000]);

    $response = $this->getJson(route('customers.index', ['over_credit_limit' => true]))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.current_balance'))->toEqual(15000);
});

it('sorts customers by name', function () {
    Customer::factory()->create(['name' => 'Z Customer']);
    Customer::factory()->create(['name' => 'A Customer']);

    $response = $this->getJson(route('customers.index', ['sort_by' => 'name', 'sort_direction' => 'asc']))
        ->assertOk();

    expect($response->json('data.0.name'))->toBe('A Customer')
        ->and($response->json('data.1.name'))->toBe('Z Customer');
});

it('paginates results', function () {
    Customer::factory()->count(7)->create();

    $response = $this->getJson(route('customers.index', ['per_page' => 3]))->assertOk();

    expect($response->json('data'))->toHaveCount(3)
        ->and($response->json('pagination.total'))->toBe(7)
        ->and($response->json('pagination.last_page'))->toBe(3);
});

it('returns salespersons from Sales department only', function () {
    $accountingDept = \App\Models\Setups\Employees\Department::factory()->create(['name' => 'Accounting']);
    $shippingDept   = \App\Models\Setups\Employees\Department::factory()->create(['name' => 'Shipping']);

    Employee::factory()->create(['department_id' => $this->salesDepartment->id, 'is_active' => true]);
    Employee::factory()->create(['department_id' => $this->salesDepartment->id, 'is_active' => true]);
    Employee::factory()->create(['department_id' => $accountingDept->id, 'is_active' => true]);
    Employee::factory()->create(['department_id' => $shippingDept->id,   'is_active' => true]);

    $salespersons = $this->getJson(route('customers.salespersons'))
        ->assertOk()
        ->json('data');

    // 2 new + 1 from beforeEach = 3 total from Sales
    expect($salespersons)->toHaveCount(3);
    foreach ($salespersons as $sp) {
        expect($sp['department']['name'])->toBe('Sales');
    }
});
