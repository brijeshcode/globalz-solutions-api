<?php

use App\Helpers\CustomersHelper;
use App\Models\Customers\Customer;
use App\Models\Customers\Sale;
use Tests\Feature\Customers\Customers\Concerns\HasCustomerSetup;

uses(HasCustomerSetup::class);

beforeEach(function () {
    $this->setUpCustomers();
});

it('does not double-count child balances in total when combine is on', function () {
    $this->enableCombineBalance();
    $parent = Customer::factory()->create(['current_balance' => 0]);
    $child  = Customer::factory()->create(['parent_id' => $parent->id, 'current_balance' => 0]);

    Sale::factory()->create(['customer_id' => $parent->id, 'total_usd' => 100, 'approved_by' => $this->admin->id]);
    Sale::factory()->create(['customer_id' => $child->id,  'total_usd' => 40,  'approved_by' => $this->admin->id]);

    CustomersHelper::recalculateCurrentBalance($parent);
    CustomersHelper::recalculateCurrentBalance($child);

    $total = $this->getJson(route('customers.stats'))->assertOk()->json('data.total_customer_balance');

    // parent -140 + child 0 = -140 (each sale counted once)
    expect((float) $total)->toEqual(-140.0);
});

it('excludes stale child balances from the total when combine is on', function () {
    // Simulate a child that still holds a stored balance from before the flag / a recalc.
    $this->enableCombineBalance();
    $parent = Customer::factory()->create(['current_balance' => -140]);
    Customer::factory()->create(['parent_id' => $parent->id, 'current_balance' => 500]);

    $total = $this->getJson(route('customers.stats'))->assertOk()->json('data.total_customer_balance');

    // Child (500) is excluded; only the parent's combined balance counts.
    expect((float) $total)->toEqual(-140.0);
});
