<?php

use App\Helpers\CustomersHelper;
use App\Models\Customers\Customer;
use App\Models\Setting;
use Tests\Feature\Customers\Customers\Concerns\HasCustomerSetup;

uses(HasCustomerSetup::class);

beforeEach(function () {
    $this->setUpCustomers();
});

it('defaults combine setting to false', function () {
    expect(CustomersHelper::combineEnabled())->toBeFalse();
});

it('returns the customer itself as balance owner when combine is off', function () {
    $parent = Customer::factory()->create();
    $child  = Customer::factory()->create(['parent_id' => $parent->id]);

    expect(CustomersHelper::balanceOwner($child)->id)->toBe($child->id);
});

it('returns the parent as balance owner for a child when combine is on', function () {
    Setting::set('customers', 'combine_parent_child_balance', true, Setting::TYPE_BOOLEAN);
    $parent = Customer::factory()->create();
    $child  = Customer::factory()->create(['parent_id' => $parent->id]);

    expect(CustomersHelper::balanceOwner($child)->id)->toBe($parent->id);
    expect(CustomersHelper::balanceOwner($parent)->id)->toBe($parent->id);
});

it('applies a live balance delta to the child itself when combine is off', function () {
    $parent = Customer::factory()->create(['current_balance' => 0]);
    $child  = Customer::factory()->create(['parent_id' => $parent->id, 'current_balance' => 0]);

    CustomersHelper::addBalance($child, 100);

    expect($child->fresh()->current_balance)->toEqual(100.0);
    expect($parent->fresh()->current_balance)->toEqual(0.0);
});

it('routes a live balance delta to the parent when combine is on', function () {
    Setting::set('customers', 'combine_parent_child_balance', true, Setting::TYPE_BOOLEAN);
    $parent = Customer::factory()->create(['current_balance' => 0]);
    $child  = Customer::factory()->create(['parent_id' => $parent->id, 'current_balance' => 0]);

    CustomersHelper::addBalance($child, 100);
    CustomersHelper::removeBalance($child, 30);

    expect($parent->fresh()->current_balance)->toEqual(70.0);
    expect($child->fresh()->current_balance)->toEqual(0.0);
});
