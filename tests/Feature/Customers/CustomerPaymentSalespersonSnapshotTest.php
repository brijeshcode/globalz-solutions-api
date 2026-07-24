<?php

use App\Models\Customers\Customer;
use App\Models\Customers\CustomerPayment;
use App\Models\Employees\Employee;

it('snapshots the customer salesperson at creation and keeps it when the customer is reassigned', function () {
    $salesmanA = Employee::factory()->create();
    $salesmanB = Employee::factory()->create();
    $customer = Customer::factory()->create(['salesperson_id' => $salesmanA->id]);

    // No salesperson_id passed -> the creating hook snapshots it from the customer.
    $payment = CustomerPayment::factory()->create(['customer_id' => $customer->id]);

    expect($payment->salesperson_id)->toBe($salesmanA->id);

    // Reassign the customer to a different salesperson.
    $customer->update(['salesperson_id' => $salesmanB->id]);

    // The payment keeps its original salesperson (snapshot), not the customer's new one.
    expect($payment->fresh()->salesperson_id)->toBe($salesmanA->id);
});

it('re-snapshots the salesperson from the new customer when the payment is moved to a different customer', function () {
    $salesmanA = Employee::factory()->create();
    $salesmanB = Employee::factory()->create();
    $customerA = Customer::factory()->create(['salesperson_id' => $salesmanA->id]);
    $customerB = Customer::factory()->create(['salesperson_id' => $salesmanB->id]);

    $payment = CustomerPayment::factory()->create(['customer_id' => $customerA->id]);
    expect($payment->salesperson_id)->toBe($salesmanA->id);

    // Edit the payment onto a different customer (reload first, as a real edit flow would).
    $payment = CustomerPayment::find($payment->id);
    $payment->update(['customer_id' => $customerB->id]);

    expect($payment->fresh()->salesperson_id)->toBe($salesmanB->id);
});

it('keeps an explicit salesperson_id even when the customer is changed in the same edit', function () {
    $salesmanA = Employee::factory()->create();
    $salesmanB = Employee::factory()->create();
    $explicit = Employee::factory()->create();
    $customerA = Customer::factory()->create(['salesperson_id' => $salesmanA->id]);
    $customerB = Customer::factory()->create(['salesperson_id' => $salesmanB->id]);

    $payment = CustomerPayment::factory()->create(['customer_id' => $customerA->id]);

    // Change customer AND set salesperson explicitly in one edit -> explicit wins.
    $payment = CustomerPayment::find($payment->id);
    $payment->update(['customer_id' => $customerB->id, 'salesperson_id' => $explicit->id]);

    expect($payment->fresh()->salesperson_id)->toBe($explicit->id);
});

it('respects an explicitly provided salesperson_id over the customer snapshot', function () {
    $customerSalesman = Employee::factory()->create();
    $explicitSalesman = Employee::factory()->create();
    $customer = Customer::factory()->create(['salesperson_id' => $customerSalesman->id]);

    $payment = CustomerPayment::factory()->create([
        'customer_id'    => $customer->id,
        'salesperson_id' => $explicitSalesman->id,
    ]);

    expect($payment->salesperson_id)->toBe($explicitSalesman->id);
});
