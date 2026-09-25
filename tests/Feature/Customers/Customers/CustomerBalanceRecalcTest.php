<?php

use App\Helpers\CustomersHelper;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerCreditDebitNote;
use App\Models\Customers\Sale;
use App\Models\Setting;
use Tests\Feature\Customers\Customers\Concerns\HasCustomerSetup;

uses(HasCustomerSetup::class);

beforeEach(function () {
    $this->setUpCustomers();
});

it('recalculates own balance from transactions when combine is off', function () {
    // Sale (debit) and credit note (credit) — neither touches accounts, so no extra setup needed.
    $customer = Customer::factory()->create(['current_balance' => 999]);
    Sale::factory()->create([
        'customer_id' => $customer->id, 'total_usd' => 200, 'approved_by' => $this->admin->id,
    ]);
    CustomerCreditDebitNote::factory()->credit()->create([
        'customer_id' => $customer->id, 'amount_usd' => 50,
    ]);

    $balance = CustomersHelper::recalculateCurrentBalance($customer);

    // (credit_notes) - (sales) = 50 - 200 = -150
    expect($balance)->toEqual(-150.0);
    expect($customer->fresh()->current_balance)->toEqual(-150.0);
});

it('folds children into the parent and zeroes the child when combine is on', function () {
    $this->enableCombineBalance();
    $parent = Customer::factory()->create(['current_balance' => 0]);
    $child  = Customer::factory()->create(['parent_id' => $parent->id, 'current_balance' => 0]);

    Sale::factory()->create(['customer_id' => $parent->id, 'total_usd' => 100, 'approved_by' => $this->admin->id]);
    Sale::factory()->create(['customer_id' => $child->id,  'total_usd' => 40,  'approved_by' => $this->admin->id]);

    $parentBalance = CustomersHelper::recalculateCurrentBalance($parent);
    $childBalance  = CustomersHelper::recalculateCurrentBalance($child);

    expect($parentBalance)->toEqual(-140.0); // -(100 + 40)
    expect($childBalance)->toEqual(0.0);
    expect($child->fresh()->current_balance)->toEqual(0.0);
});

it('admin recalc zeroes a combined child and folds it into the parent', function () {
    $this->enableCombineBalance();
    $parent = Customer::factory()->create(['current_balance' => 5]);
    $child  = Customer::factory()->create(['parent_id' => $parent->id, 'current_balance' => 5]);
    Sale::factory()->create(['customer_id' => $child->id, 'total_usd' => 40, 'approved_by' => $this->admin->id]);

    $controller = app(\App\Http\Controllers\Api\Customers\CustomerStatmentController::class);
    $controller->processCustomerBalanceRecalculation($child->fresh());
    $controller->processCustomerBalanceRecalculation($parent->fresh());

    expect($child->fresh()->current_balance)->toEqual(0.0);
    expect($parent->fresh()->current_balance)->toEqual(-40.0);
});

it('monthly service respects the combine rule for a child', function () {
    $this->enableCombineBalance();
    $parent = Customer::factory()->create(['current_balance' => 0]);
    $child  = Customer::factory()->create(['parent_id' => $parent->id, 'current_balance' => 123]);
    Sale::factory()->create(['customer_id' => $child->id, 'total_usd' => 10, 'approved_by' => $this->admin->id]);

    \App\Services\Customers\CustomerBalanceService::updateCustomerCurrentBalance($child->id);

    expect($child->fresh()->current_balance)->toEqual(0.0);
});
