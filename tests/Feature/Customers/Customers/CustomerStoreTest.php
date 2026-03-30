<?php

use App\Models\Customers\Customer;
use App\Models\Employees\Employee;
use App\Models\Setups\Employees\Department;
use Tests\Feature\Customers\Customers\Concerns\HasCustomerSetup;

uses(HasCustomerSetup::class);

beforeEach(function () {
    $this->setUpCustomers();
});

it('creates a customer with minimum required fields', function () {
    $this->postJson(route('customers.store'), $this->customerPayload(['name' => 'Test Customer', 'is_active' => true]))
        ->assertCreated()
        ->assertJsonStructure(['message', 'data' => ['id', 'code', 'name', 'is_active']]);

    $customer = Customer::where('name', 'Test Customer')->first();
    $this->assertDatabaseHas('customers', ['name' => 'Test Customer', 'is_active' => true]);
    expect((int) $customer->code)->toBeGreaterThanOrEqual(41101364);
});

it('creates a customer with all fields', function () {
    $this->postJson(route('customers.store'), $this->customerPayload([
        'name'                     => 'Complete Customer',
        'current_balance'          => 7500.75,
        'address'                  => '123 Customer Street, City',
        'telephone'                => '01-234-5678',
        'url'                      => 'https://customer.com',
        'email'                    => 'customer@example.com',
        'contact_name'             => 'John Customer',
        'gps_coordinates'          => '33.9024493,35.5750987',
        'mof_tax_number'           => '123456789',
        'customer_payment_term_id' => $this->customerPaymentTerm->id,
        'discount_percentage'      => 5.5,
        'credit_limit'             => 10000.00,
        'notes'                    => 'Important customer notes',
        'is_active'                => true,
    ]))->assertCreated()
       ->assertJson(['data' => ['name' => 'Complete Customer', 'email' => 'customer@example.com', 'discount_percentage' => 5.5, 'credit_limit' => 10000.00]]);

    $customer = Customer::where('name', 'Complete Customer')->first();
    expect($customer->code)->not()->toBeNull()
        ->and((int) $customer->code)->toBeGreaterThanOrEqual(41101364);

    $this->assertDatabaseHas('customers', ['name' => 'Complete Customer', 'email' => 'customer@example.com', 'credit_limit' => 10000.00]);
});

it('ignores provided code and always auto-generates one', function () {
    $this->postJson(route('customers.store'), $this->customerPayload([
        'name' => 'Custom Code Customer',
        'code' => '99999999',
    ]))->assertCreated();

    $customer = Customer::where('name', 'Custom Code Customer')->first();
    expect($customer->code)->not()->toBe('99999999')
        ->and((int) $customer->code)->toBeGreaterThanOrEqual(41101364);
});

it('sets created_by and updated_by automatically', function () {
    $customer = Customer::factory()->create(['name' => 'Test Customer']);

    expect($customer->created_by)->toBe($this->admin->id)
        ->and($customer->updated_by)->toBe($this->admin->id);

    $customer->update(['name' => 'Updated Customer']);
    expect($customer->fresh()->updated_by)->toBe($this->admin->id);
});

it('handles concurrent customer creation with unique codes', function () {
    $customers = [];
    for ($i = 0; $i < 5; $i++) {
        $customers[] = Customer::factory()->create(['name' => "Customer {$i}"]);
    }

    $codes = collect($customers)->map(fn ($c) => (int) $c->code)->sort()->values();
    for ($i = 1; $i < count($codes); $i++) {
        expect($codes[$i])->toBeGreaterThan($codes[$i - 1]);
    }
});

it('accepts valid GPS coordinates', function () {
    $formats = ['33.9024493,35.5750987', '-33.9024493,-35.5750987', '0.0000000,0.0000000'];

    foreach ($formats as $index => $gps) {
        $this->postJson(route('customers.store'), $this->customerPayload([
            'name'            => "GPS Customer {$index}",
            'gps_coordinates' => $gps,
        ]))->assertCreated();

        $this->assertDatabaseHas('customers', ['name' => "GPS Customer {$index}", 'gps_coordinates' => $gps]);
    }
});

// --- Validation ---

it('requires name field', function () {
    $this->postJson(route('customers.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('validates foreign key references', function () {
    $this->postJson(route('customers.store'), [
        'name'                     => 'Test Customer',
        'customer_type_id'         => 99999,
        'customer_group_id'        => 99999,
        'customer_province_id'     => 99999,
        'customer_zone_id'         => 99999,
        'salesperson_id'           => 99999,
        'customer_payment_term_id' => 99999,
    ])->assertUnprocessable()
      ->assertJsonValidationErrors(['customer_type_id', 'customer_group_id', 'customer_province_id', 'customer_zone_id', 'salesperson_id', 'customer_payment_term_id']);
});

it('validates salesperson must be from Sales department', function () {
    $accountingDept = Department::factory()->create(['name' => 'Accounting']);
    $accountingEmp  = Employee::factory()->create(['department_id' => $accountingDept->id]);

    $this->postJson(route('customers.store'), ['name' => 'Test Customer', 'salesperson_id' => $accountingEmp->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['salesperson_id']);
});

it('validates email format', function () {
    $this->postJson(route('customers.store'), ['name' => 'Test Customer', 'email' => 'invalid-email'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('validates URL format', function () {
    $this->postJson(route('customers.store'), ['name' => 'Test Customer', 'url' => 'not-a-valid-url'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['url']);
});

it('validates GPS coordinates format', function () {
    $this->postJson(route('customers.store'), ['name' => 'Test Customer', 'gps_coordinates' => 'invalid-coordinates'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['gps_coordinates']);
});

it('validates discount percentage range', function () {
    $this->postJson(route('customers.store'), ['name' => 'Test Customer', 'discount_percentage' => 150])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['discount_percentage']);
});

it('validates credit_limit must be non-negative', function () {
    $this->postJson(route('customers.store'), ['name' => 'Test Customer', 'credit_limit' => -100])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['credit_limit']);
});

it('validates maximum string field lengths', function () {
    $this->postJson(route('customers.store'), [
        'name'      => str_repeat('a', 256),
        'telephone' => str_repeat('1', 21),
        'mobile'    => str_repeat('2', 21),
    ])->assertUnprocessable()
      ->assertJsonValidationErrors(['name', 'telephone', 'mobile']);
});
