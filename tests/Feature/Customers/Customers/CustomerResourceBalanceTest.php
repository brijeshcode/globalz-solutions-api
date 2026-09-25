<?php

use App\Models\Customers\Customer;
use App\Models\Setting;
use Tests\Feature\Customers\Customers\Concerns\HasCustomerSetup;

uses(HasCustomerSetup::class);

beforeEach(function () {
    $this->setUpCustomers();
});

it('shows a child its own balance when combine is off', function () {
    $parent = Customer::factory()->create();
    $child  = Customer::factory()->create(['parent_id' => $parent->id, 'current_balance' => 250]);

    $this->getJson(route('customers.show', $child))
        ->assertOk()
        ->assertJsonPath('data.current_balance', 250);
});

it('shows a child zero balance when combine is on', function () {
    $this->enableCombineBalance();
    $parent = Customer::factory()->create();
    $child  = Customer::factory()->create(['parent_id' => $parent->id, 'current_balance' => 250]);

    $this->getJson(route('customers.show', $child))
        ->assertOk()
        ->assertJsonPath('data.current_balance', 0);
});
