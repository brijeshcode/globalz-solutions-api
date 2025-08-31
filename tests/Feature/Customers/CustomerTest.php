<?php

use App\Models\Customers\Customer;
use App\Models\Setting;
use App\Models\Setups\Customers\CustomerType;
use App\Models\Setups\Customers\CustomerGroup;
use App\Models\Setups\Customers\CustomerProvince;
use App\Models\Setups\Customers\CustomerZone;
use App\Models\Setups\Customers\CustomerPaymentTerm;
use App\Models\Employees\Employee;
use App\Models\Setups\Employees\Department;
use App\Models\User;

uses()->group('api', 'setup', 'setup.customers', 'customers');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
    
    // Create customer code counter setting (starting from 50000000)
    Setting::create([
        'group_name' => 'customers',
        'key_name' => 'code_counter', 
        'value' => '50000000',
        'data_type' => 'number',
        'description' => 'Customer code counter starting from 50000000'
    ]);
    
    // Create related models for testing
    $this->customerType = CustomerType::factory()->create();
    $this->customerGroup = CustomerGroup::factory()->create();
    $this->customerProvince = CustomerProvince::factory()->create();
    $this->customerZone = CustomerZone::factory()->create();
    $this->customerPaymentTerm = CustomerPaymentTerm::factory()->create();
    
    // Create Sales department and salesperson
    $this->salesDepartment = Department::factory()->create(['name' => 'Sales']);
    $this->salesperson = Employee::factory()->create([
        'department_id' => $this->salesDepartment->id,
        'is_active' => true
    ]);
    
    // Helper method for base customer data
    $this->getBaseCustomerData = function ($overrides = []) {
        return array_merge([
            'name' => 'Test Customer',
            'customer_type_id' => $this->customerType->id,
            'customer_group_id' => $this->customerGroup->id,
            'customer_province_id' => $this->customerProvince->id,
            'customer_zone_id' => $this->customerZone->id,
            'salesperson_id' => $this->salesperson->id,
            'opening_balance' => 1000.00,
            'current_balance' => 1500.00,
        ], $overrides);
    };
});

describe('Customers API', function () {
    it('can list customers', function () {
        Customer::factory()->count(3)->create([
            'customer_type_id' => $this->customerType->id,
            'customer_group_id' => $this->customerGroup->id,
        ]);

        $response = $this->getJson(route('customers.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'name',
                        'customer_type',
                        'customer_group',
                        'current_balance',
                        'is_active',
                    ]
                ],
                'pagination'
            ]);

        expect($response->json('data'))->toHaveCount(3);
    });

    it('can create a customer with minimum required fields', function () {
        $data = [
            'name' => 'Test Customer',
            'is_active' => true,
        ];

        $response = $this->postJson(route('customers.store'), $data);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'code',
                    'name',
                    'is_active',
                ]
            ]);

        $this->assertDatabaseHas('customers', [
            'name' => 'Test Customer',
            'is_active' => true,
        ]);

        // Check if code was auto-generated starting from 50000000
        $customer = Customer::where('name', 'Test Customer')->first();
        expect((int)$customer->code)->toBeGreaterThanOrEqual(50000000);
    });

    it('can create a customer with all fields', function () {
        $data = [
            'name' => 'Complete Customer',
            'customer_type_id' => $this->customerType->id,
            'customer_group_id' => $this->customerGroup->id,
            'customer_province_id' => $this->customerProvince->id,
            'customer_zone_id' => $this->customerZone->id,
            'opening_balance' => 5000.50,
            'current_balance' => 7500.75,
            'address' => '123 Customer Street, City',
            'city' => 'Test City',
            'telephone' => '01-234-5678',
            'mobile' => '050-123-4567',
            'url' => 'https://customer.com',
            'email' => 'customer@example.com',
            'contact_name' => 'John Customer',
            'gps_coordinates' => '33.9024493,35.5750987',
            'mof_tax_number' => '123456789',
            'salesperson_id' => $this->salesperson->id,
            'customer_payment_term_id' => $this->customerPaymentTerm->id,
            'discount_percentage' => 5.5,
            'credit_limit' => 10000.00,
            'notes' => 'Important customer notes',
            'is_active' => true,
        ];

        $response = $this->postJson(route('customers.store'), $data);

        $response->assertCreated()
            ->assertJson([
                'data' => [
                    'name' => 'Complete Customer',
                    'opening_balance' => 5000.50,
                    'email' => 'customer@example.com',
                    'discount_percentage' => 5.5,
                    'credit_limit' => 10000.00,
                ]
            ]);

        // Verify code was auto-generated
        $customer = Customer::where('name', 'Complete Customer')->first();
        expect($customer->code)->not()->toBeNull();
        expect((int)$customer->code)->toBeGreaterThanOrEqual(50000000);

        $this->assertDatabaseHas('customers', [
            'name' => 'Complete Customer',
            'email' => 'customer@example.com',
            'credit_limit' => 10000.00,
        ]);
    });

    it('auto-generates customer codes when not provided', function () {
        $data = [
            'name' => 'Auto Code Customer',
        ];

        $response = $this->postJson(route('customers.store'), $data);

        $response->assertCreated();
        
        $customer = Customer::where('name', 'Auto Code Customer')->first();
        expect($customer->code)->not()->toBeNull();
        expect((int)$customer->code)->toBeGreaterThanOrEqual(50000000);
    });

    it('ignores provided code and always generates new one', function () {
        // Even if user somehow sends a code, it should be ignored
        $data = [
            'name' => 'Custom Code Customer',
            'code' => '99999999', // This should be ignored
        ];

        $response = $this->postJson(route('customers.store'), $data);

        $response->assertCreated();
        
        $customer = Customer::where('name', 'Custom Code Customer')->first();
        expect($customer->code)->not()->toBe('99999999'); // Should not use provided code
        expect((int)$customer->code)->toBeGreaterThanOrEqual(50000000); // Should be auto-generated
    });

    it('can show a customer', function () {
        $customer = Customer::factory()->create([
            'customer_type_id' => $this->customerType->id,
            'customer_group_id' => $this->customerGroup->id,
            'salesperson_id' => $this->salesperson->id,
        ]);

        $response = $this->getJson(route('customers.show', $customer));

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $customer->id,
                    'code' => $customer->code,
                    'name' => $customer->name,
                ]
            ]);
    });

    it('can update a customer', function () {
        $customer = Customer::factory()->create([
            'customer_type_id' => $this->customerType->id,
            'customer_group_id' => $this->customerGroup->id,
        ]);
        
        $originalCode = $customer->code;
        
        $data = [
            'name' => 'Updated Customer',
            'email' => 'updated@example.com',
            'current_balance' => 2500.00,
            'notes' => 'Updated notes',
        ];

        $response = $this->putJson(route('customers.update', $customer), $data);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'code' => $originalCode, // Code should remain unchanged
                    'name' => 'Updated Customer',
                    'email' => 'updated@example.com',
                    'current_balance' => 2500.00,
                ]
            ]);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'code' => $originalCode, // Verify code wasn't changed
            'name' => 'Updated Customer',
            'email' => 'updated@example.com',
        ]);
    });

    it('code cannot be updated once set', function () {
        $customer = Customer::factory()->create();
        $originalCode = $customer->code;
        
        // Try to update with a code field (should be ignored)
        $data = [
            'name' => 'Updated Customer',
            'code' => '99999999', // This should be ignored
        ];

        $response = $this->putJson(route('customers.update', $customer), $data);

        $response->assertOk();
        
        // Verify code wasn't changed
        $updatedCustomer = $customer->fresh();
        expect($updatedCustomer->code)->toBe($originalCode);
        expect($updatedCustomer->code)->not()->toBe('99999999');
    });

    it('can delete a customer', function () {
        $customer = Customer::factory()->create([
            'customer_type_id' => $this->customerType->id,
        ]);

        $response = $this->deleteJson(route('customers.destroy', $customer));

        $response->assertStatus(204);
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    });

    it('cannot delete customer with child customers', function () {
        $parentCustomer = Customer::factory()->create();
        $childCustomer = Customer::factory()->create([
            'parent_id' => $parentCustomer->id
        ]);

        $response = $this->deleteJson(route('customers.destroy', $parentCustomer));

        $response->assertUnprocessable()
            ->assertJson([
                'message' => 'Cannot delete customer with child customers. Please handle child customers first.'
            ]);

        $this->assertDatabaseHas('customers', [
            'id' => $parentCustomer->id,
            'deleted_at' => null
        ]);
    });

    it('validates required fields when creating', function () {
        $response = $this->postJson(route('customers.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates foreign key references', function () {
        $response = $this->postJson(route('customers.store'), [
            'name' => 'Test Customer',
            'customer_type_id' => 99999,
            'customer_group_id' => 99999,
            'customer_province_id' => 99999,
            'customer_zone_id' => 99999,
            'salesperson_id' => 99999,
            'customer_payment_term_id' => 99999,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'customer_type_id',
                'customer_group_id',
                'customer_province_id',
                'customer_zone_id',
                'salesperson_id',
                'customer_payment_term_id'
            ]);
    });

    it('validates salesperson is from Sales department', function () {
        $accountingDept = Department::factory()->create(['name' => 'Accounting']);
        $accountingEmployee = Employee::factory()->create([
            'department_id' => $accountingDept->id
        ]);

        $response = $this->postJson(route('customers.store'), [
            'name' => 'Test Customer',
            'salesperson_id' => $accountingEmployee->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['salesperson_id']);
    });

    it('validates email format', function () {
        $response = $this->postJson(route('customers.store'), [
            'name' => 'Test Customer',
            'email' => 'invalid-email',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('validates URL format', function () {
        $response = $this->postJson(route('customers.store'), [
            'name' => 'Test Customer',
            'url' => 'not-a-valid-url',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['url']);
    });

    it('validates GPS coordinates format', function () {
        $response = $this->postJson(route('customers.store'), [
            'name' => 'Test Customer',
            'gps_coordinates' => 'invalid-coordinates',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['gps_coordinates']);
    });

    it('validates discount percentage range', function () {
        $response = $this->postJson(route('customers.store'), [
            'name' => 'Test Customer',
            'discount_percentage' => 150, // Over 100%
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['discount_percentage']);
    });

    it('validates numeric fields are within range', function () {
        $response = $this->postJson(route('customers.store'), [
            'name' => 'Test Customer',
            'opening_balance' => -999999999999.99, // Out of range
            'credit_limit' => -100, // Negative credit limit
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['opening_balance', 'credit_limit']);
    });

    it('can get next available code', function () {
        $response = $this->getJson(route('customers.next-code'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'code',
                    'is_available',
                    'message'
                ]
            ]);

        $code = $response->json('data.code');
        expect((int)$code)->toBeGreaterThanOrEqual(50000000);
        expect($response->json('data.is_available'))->toBe(true);
    });

    it('can get salespersons from Sales department only', function () {
        // Create employees in different departments
        $accountingDept = Department::factory()->create(['name' => 'Accounting']);
        $shippingDept = Department::factory()->create(['name' => 'Shipping']);
        
        $salesEmployee1 = Employee::factory()->create([
            'department_id' => $this->salesDepartment->id,
            'is_active' => true
        ]);
        $salesEmployee2 = Employee::factory()->create([
            'department_id' => $this->salesDepartment->id,
            'is_active' => true
        ]);
        $accountingEmployee = Employee::factory()->create([
            'department_id' => $accountingDept->id,
            'is_active' => true
        ]);
        $shippingEmployee = Employee::factory()->create([
            'department_id' => $shippingDept->id,
            'is_active' => true
        ]);

        $response = $this->getJson(route('customers.salespersons'));

        $response->assertOk();
        $salespersons = $response->json('data');

        // Should only return employees from Sales department (including the one from beforeEach)
        expect($salespersons)->toHaveCount(3);
        foreach ($salespersons as $salesperson) {
            expect($salesperson['department']['name'])->toBe('Sales');
        }
    });

    it('can search customers by name', function () {
        Customer::factory()->create(['name' => 'Searchable Customer']);
        Customer::factory()->create(['name' => 'Another Customer']);

        $response = $this->getJson(route('customers.index', ['search' => 'Searchable']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['name'])->toBe('Searchable Customer');
    });

    it('can search customers by code', function () {
        $customer1 = Customer::factory()->create(['name' => 'First Customer']);
        $customer2 = Customer::factory()->create(['name' => 'Second Customer']);

        $response = $this->getJson(route('customers.index', ['search' => $customer1->code]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['code'])->toBe($customer1->code);
    });

    it('can filter by active status', function () {
        Customer::factory()->create(['is_active' => true]);
        Customer::factory()->create(['is_active' => false]);

        $response = $this->getJson(route('customers.index', ['is_active' => true]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['is_active'])->toBe(true);
    });

    it('can filter by customer type', function () {
        $retailType = CustomerType::factory()->create(['name' => 'Retail']);
        $wholesaleType = CustomerType::factory()->create(['name' => 'Wholesale']);
        
        Customer::factory()->create(['customer_type_id' => $retailType->id]);
        Customer::factory()->create(['customer_type_id' => $wholesaleType->id]);

        $response = $this->getJson(route('customers.index', ['customer_type_id' => $retailType->id]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['customer_type']['id'])->toBe($retailType->id);
    });

    it('can filter by customer group', function () {
        $vipGroup = CustomerGroup::factory()->create(['name' => 'VIP']);
        $regularGroup = CustomerGroup::factory()->create(['name' => 'Regular']);
        
        Customer::factory()->create(['customer_group_id' => $vipGroup->id]);
        Customer::factory()->create(['customer_group_id' => $regularGroup->id]);

        $response = $this->getJson(route('customers.index', ['customer_group_id' => $vipGroup->id]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['customer_group']['id'])->toBe($vipGroup->id);
    });

    it('can filter by salesperson', function () {
        $otherSalesperson = Employee::factory()->create([
            'department_id' => $this->salesDepartment->id
        ]);
        
        Customer::factory()->create(['salesperson_id' => $this->salesperson->id]);
        Customer::factory()->create(['salesperson_id' => $otherSalesperson->id]);

        $response = $this->getJson(route('customers.index', ['salesperson_id' => $this->salesperson->id]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['salesperson']['id'])->toBe($this->salesperson->id);
    });

    it('can filter by balance range', function () {
        Customer::factory()->create(['current_balance' => 1000]);
        Customer::factory()->create(['current_balance' => 5000]);
        Customer::factory()->create(['current_balance' => 10000]);

        $response = $this->getJson(route('customers.index', [
            'min_balance' => 2000,
            'max_balance' => 8000
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['current_balance'])->toBe(5000);
    });

    it('can filter customers over credit limit', function () {
        Customer::factory()->create([
            'current_balance' => 5000,
            'credit_limit' => 10000
        ]);
        Customer::factory()->create([
            'current_balance' => 15000,
            'credit_limit' => 10000
        ]);

        $response = $this->getJson(route('customers.index', ['over_credit_limit' => true]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['current_balance'])->toBe(15000);
    });

    it('can filter by balance status', function () {
        Customer::factory()->create(['current_balance' => 1000]); // credit
        Customer::factory()->create(['current_balance' => -500]); // debit
        Customer::factory()->create(['current_balance' => 0]); // balanced

        $response = $this->getJson(route('customers.index', ['balance_status' => 'credit']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['current_balance'])->toBeGreaterThan(0);
    });

    it('can sort customers by name', function () {
        Customer::factory()->create(['name' => 'Z Customer']);
        Customer::factory()->create(['name' => 'A Customer']);

        $response = $this->getJson(route('customers.index', [
            'sort_by' => 'name',
            'sort_direction' => 'asc'
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data[0]['name'])->toBe('A Customer');
        expect($data[1]['name'])->toBe('Z Customer');
    });

    it('can list trashed customers', function () {
        $customer = Customer::factory()->create([
            'customer_type_id' => $this->customerType->id,
        ]);
        $customer->delete();

        $response = $this->getJson(route('customers.trashed'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'name',
                        'customer_type',
                        'is_active',
                    ]
                ],
                'pagination'
            ]);

        expect($response->json('data'))->toHaveCount(1);
    });

    it('can restore a trashed customer', function () {
        $customer = Customer::factory()->create();
        $customer->delete();

        $response = $this->patchJson(route('customers.restore', $customer->id));

        $response->assertOk();
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'deleted_at' => null,
        ]);
    });

    it('can force delete a trashed customer', function () {
        $customer = Customer::factory()->create();
        $customer->delete();

        $response = $this->deleteJson(route('customers.force-delete', $customer->id));

        $response->assertStatus(204);
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    });

    it('can get customer statistics', function () {
        Customer::factory()->count(5)->create(['is_active' => true]);
        Customer::factory()->count(2)->create(['is_active' => false]);

        $response = $this->getJson(route('customers.stats'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'total_customers',
                    'active_customers',
                    'inactive_customers',
                    'trashed_customers',
                    'customers_with_balance',
                    'customers_over_credit_limit',
                    'total_customer_balance',
                    'customers_by_type',
                    'customers_by_province',
                    'customers_by_zone',
                ]
            ]);

        $stats = $response->json('data');
        expect($stats['total_customers'])->toBe(7);
        expect($stats['active_customers'])->toBe(5);
        expect($stats['inactive_customers'])->toBe(2);
    });

    it('generates sequential customer codes', function () {
        // Clear existing customers
        Customer::withTrashed()->forceDelete();

        // First customer
        $response1 = $this->postJson(route('customers.store'), [
            'name' => 'First Customer',
        ]);
        $response1->assertCreated();
        $code1 = (int) $response1->json('data.code');

        // Second customer
        $response2 = $this->postJson(route('customers.store'), [
            'name' => 'Second Customer',
        ]);
        $response2->assertCreated();
        $code2 = (int) $response2->json('data.code');

        expect($code1)->toBeGreaterThanOrEqual(50000000);
        expect($code2)->toBe($code1 + 1); // strictly sequential
    });

    it('validates parent customer cannot be self', function () {
        $customer = Customer::factory()->create();

        $response = $this->putJson(route('customers.update', $customer), [
            'name' => 'Updated Customer',
            'parent_id' => $customer->id, // Self-parent
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id']);
    });

    it('validates parent customer cannot have child as parent (circular)', function () {
        $parent = Customer::factory()->create();
        $child = Customer::factory()->create(['parent_id' => $parent->id]);

        $response = $this->putJson(route('customers.update', $parent), [
            'name' => 'Updated Parent',
            'parent_id' => $child->id, // Circular reference
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id']);
    });

    it('validates cannot deactivate customer with active children', function () {
        $parent = Customer::factory()->create(['is_active' => true]);
        $child = Customer::factory()->create([
            'parent_id' => $parent->id,
            'is_active' => true
        ]);

        $response = $this->putJson(route('customers.update', $parent), [
            'name' => $parent->name,
            'is_active' => false, // Try to deactivate parent
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['is_active']);
    });

    it('sets created_by and updated_by fields automatically', function () {
        $customer = Customer::factory()->create(['name' => 'Test Customer']);

        expect($customer->created_by)->toBe($this->user->id);
        expect($customer->updated_by)->toBe($this->user->id);

        // Test update tracking
        $customer->update(['name' => 'Updated Customer']);
        expect($customer->fresh()->updated_by)->toBe($this->user->id);
    });

    it('returns 404 for non-existent customer', function () {
        $response = $this->getJson(route('customers.show', 999));

        $response->assertNotFound();
    });

    it('can paginate customers', function () {
        Customer::factory()->count(7)->create();

        $response = $this->getJson(route('customers.index', ['per_page' => 3]));

        $response->assertOk();
        $data = $response->json('data');
        $pagination = $response->json('pagination');
        
        expect($data)->toHaveCount(3);
        expect($pagination['total'])->toBe(7);
        expect($pagination['per_page'])->toBe(3);
        expect($pagination['last_page'])->toBe(3);
    });

    it('handles concurrent customer creation with code generation', function () {
        // Simulate concurrent requests by creating multiple customers
        $customers = [];
        for ($i = 0; $i < 5; $i++) {
            $customers[] = Customer::factory()->create(['name' => "Customer {$i}"]);
        }

        $codes = collect($customers)->map(fn($c) => (int) $c->code)->sort()->values();
        
        // Ensure all codes are unique and sequential
        for ($i = 1; $i < count($codes); $i++) {
            expect($codes[$i])->toBeGreaterThan($codes[$i - 1]);
        }
    });

    it('validates maximum length for string fields', function () {
        $response = $this->postJson(route('customers.store'), [
            'name' => str_repeat('a', 256), // Exceeds 255 character limit
            'telephone' => str_repeat('1', 21), // Exceeds 20 character limit
            'mobile' => str_repeat('2', 21), // Exceeds 20 character limit
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'telephone', 'mobile']);
    });

    it('accepts valid GPS coordinates', function () {
        $validGpsFormats = [
            '33.9024493,35.5750987',
            '-33.9024493,-35.5750987',
            '0.0000000,0.0000000',
        ];

        foreach ($validGpsFormats as $index => $gps) {
            $response = $this->postJson(route('customers.store'), [
                'name' => "GPS Customer {$index}",
                'gps_coordinates' => $gps,
            ]);

            $response->assertCreated();
            
            $this->assertDatabaseHas('customers', [
                'name' => "GPS Customer {$index}",
                'gps_coordinates' => $gps,
            ]);
        }
    });
});

describe('Customer Code Generation Tests', function () {
    it('gets next code when current setting is 50000001', function () {
        // Set code counter to 50000001
        Setting::set('customers', 'code_counter', 50000001, 'number');
        
        $response = $this->getJson(route('customers.next-code'));
        
        $response->assertOk();
        $code = $response->json('data.code');
        
        expect((int) $code)->toBe(50000001);
        expect($response->json('data.is_available'))->toBe(true);
    });

    it('creates customer with current counter and increments correctly', function () {
        // Set counter to 50000005
        Setting::set('customers', 'code_counter', 50000005, 'number');
        
        // Create customer (should get code 50000005)
        $response = $this->postJson(route('customers.store'), [
            'name' => 'Counter Test Customer',
        ]);
        
        $response->assertCreated();
        $code = (int) $response->json('data.code');
        expect($code)->toBe(50000005);
        
        // Get next code - should be 50000006 now
        $nextCodeResponse = $this->getJson(route('customers.next-code'));
        $nextCode = $nextCodeResponse->json('data.code');
        
        expect((int) $nextCode)->toBe(50000006);
    });

    it('auto-creates code counter setting when missing', function () {
        // Delete any existing code counter setting
        Setting::where('group_name', 'customers')
            ->where('key_name', 'code_counter')
            ->delete();
        
        // Clear cache to ensure setting is gone
        Setting::clearCache();
        
        // Get next code - should auto-create setting with default value from config
        $response = $this->getJson(route('customers.next-code'));
        
        $response->assertOk();
        $nextCode = $response->json('data.code');
        
        expect((int) $nextCode)->toBe(50000000);
        
        // Verify setting was created
        $setting = Setting::where('group_name', 'customers')
            ->where('key_name', 'code_counter')
            ->first();
            
        expect($setting)->not()->toBeNull();
        expect($setting->data_type)->toBe('number');
        expect($setting->value)->toBe('50000000');
    });

    it('handles counter progression correctly', function () {
        // Set counter to 50000000
        Setting::set('customers', 'code_counter', 50000000, 'number');
        
        // Create first customer
        $response1 = $this->postJson(route('customers.store'), [
            'name' => 'First Customer',
        ]);
        
        $response1->assertCreated();
        expect((int) $response1->json('data.code'))->toBe(50000000);
        
        // Create second customer
        $response2 = $this->postJson(route('customers.store'), [
            'name' => 'Second Customer',
        ]);
        
        $response2->assertCreated();
        expect((int) $response2->json('data.code'))->toBe(50000001);
        
        // Get next code - should be 50000002
        $nextResponse = $this->getJson(route('customers.next-code'));
        $nextCode = $nextResponse->json('data.code');
        
        expect((int) $nextCode)->toBe(50000002);
    });

    it('auto-generates customer code counter setting and continues sequence when missing', function () {
        // Ensure no existing customers to get clean sequence
        Customer::withTrashed()->forceDelete();
        
        // Remove any existing code counter setting
        Setting::where('group_name', 'customers')
            ->where('key_name', 'code_counter')
            ->delete();
        
        // Clear cache to ensure setting is completely gone
        Setting::clearCache();
        
        // Verify setting doesn't exist
        $existingSetting = Setting::where('group_name', 'customers')
            ->where('key_name', 'code_counter')
            ->first();
        expect($existingSetting)->toBeNull();
        
        // Create first customer without any counter setting - should auto-generate
        $response1 = $this->postJson(route('customers.store'), [
            'name' => 'Auto Generated Counter Customer 1',
        ]);
        
        $response1->assertCreated();
        $firstCode = (int) $response1->json('data.code');
        expect($firstCode)->toBe(50000000); // Should start from default
        
        // Verify setting was auto-created
        $setting = Setting::where('group_name', 'customers')
            ->where('key_name', 'code_counter')
            ->first();
        expect($setting)->not()->toBeNull();
        expect($setting->data_type)->toBe('number');
        expect((int) $setting->value)->toBe(50000001); // Should be incremented after first customer
        
        // Create second customer - should continue sequence
        $response2 = $this->postJson(route('customers.store'), [
            'name' => 'Auto Generated Counter Customer 2',
        ]);
        
        $response2->assertCreated();
        $secondCode = (int) $response2->json('data.code');
        expect($secondCode)->toBe(50000001); // Sequential
        
        // Create third customer - should continue sequence
        $response3 = $this->postJson(route('customers.store'), [
            'name' => 'Auto Generated Counter Customer 3',
        ]);
        
        $response3->assertCreated();
        $thirdCode = (int) $response3->json('data.code');
        expect($thirdCode)->toBe(50000002); // Sequential
        
        // Verify final counter value
        $finalSetting = Setting::where('group_name', 'customers')
            ->where('key_name', 'code_counter')
            ->first();
        expect((int) $finalSetting->value)->toBe(50000003); // Ready for next customer
        
        // Test next-code endpoint also works correctly
        $nextCodeResponse = $this->getJson(route('customers.next-code'));
        $nextCodeResponse->assertOk();
        expect((int) $nextCodeResponse->json('data.code'))->toBe(50000003);
    });
});
