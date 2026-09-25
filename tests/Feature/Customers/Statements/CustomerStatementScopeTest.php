<?php

use App\Models\Customers\Customer;
use App\Models\Customers\Sale;
use Tests\Feature\Customers\Customers\Concerns\HasCustomerSetup;

uses(HasCustomerSetup::class);

beforeEach(function () {
    $this->setUpCustomers();
});

it('parent statement excludes children by default when combine is off', function () {
    $parent = Customer::factory()->create();
    $child  = Customer::factory()->create(['parent_id' => $parent->id]);
    Sale::factory()->create(['customer_id' => $parent->id, 'total_usd' => 100, 'approved_by' => $this->admin->id, 'date' => now()]);
    Sale::factory()->create(['customer_id' => $child->id,  'total_usd' => 40,  'approved_by' => $this->admin->id, 'date' => now()]);

    $data = $this->getJson(route('customers.statements.customer', $parent))->assertOk()->json('data');

    expect(collect($data)->pluck('customer.id')->unique()->values()->all())->toBe([$parent->id]);
});

it('parent statement includes children when explicitly requested', function () {
    $parent = Customer::factory()->create();
    $child  = Customer::factory()->create(['parent_id' => $parent->id]);
    Sale::factory()->create(['customer_id' => $parent->id, 'total_usd' => 100, 'approved_by' => $this->admin->id, 'date' => now()]);
    Sale::factory()->create(['customer_id' => $child->id,  'total_usd' => 40,  'approved_by' => $this->admin->id, 'date' => now()]);

    $data = $this->getJson(route('customers.statements.customer', [$parent, 'include_children_transactions' => 1]))
        ->assertOk()->json('data');

    expect(collect($data)->pluck('customer.id')->unique()->sort()->values()->all())
        ->toBe(collect([$parent->id, $child->id])->sort()->values()->all());
});

it('corrects a parent balance to parent-only on statement load when combine is off', function () {
    $parent = Customer::factory()->create();
    $child  = Customer::factory()->create(['parent_id' => $parent->id]);
    Sale::factory()->create(['customer_id' => $parent->id, 'total_usd' => 100, 'approved_by' => $this->admin->id, 'date' => now()]);
    Sale::factory()->create(['customer_id' => $child->id,  'total_usd' => 40,  'approved_by' => $this->admin->id, 'date' => now()]);

    // Simulate a stale combined balance stored under the old behaviour.
    $parent->update(['current_balance' => -140]);

    $this->getJson(route('customers.statements.customer', $parent))->assertOk();

    // Corrected to parent-only (-100 = -(own sale 100)); children excluded.
    expect((float) $parent->fresh()->current_balance)->toEqual(-100.0);
});

it('keeps a parent balance parent-only even when viewing children (combine off)', function () {
    $parent = Customer::factory()->create();
    $child  = Customer::factory()->create(['parent_id' => $parent->id]);
    Sale::factory()->create(['customer_id' => $parent->id, 'total_usd' => 100, 'approved_by' => $this->admin->id, 'date' => now()]);
    Sale::factory()->create(['customer_id' => $child->id,  'total_usd' => 40,  'approved_by' => $this->admin->id, 'date' => now()]);
    $parent->update(['current_balance' => -140]);

    // Even when the statement view includes children, the stored balance stays parent-only.
    $this->getJson(route('customers.statements.customer', [$parent, 'include_children_transactions' => 1]))->assertOk();

    expect((float) $parent->fresh()->current_balance)->toEqual(-100.0);
});
