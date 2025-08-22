<?php

use App\Models\Setups\Supplier;
use App\Models\Setups\SupplierType;
use App\Models\Setups\SupplierPaymentTerm;
use App\Models\Setups\Country;
use App\Models\Setups\Currency;
use App\Models\User;

uses()->group('api', 'setup', 'setup.suppliers', 'suppliers');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
    
    // Create related models for testing with unique names
    $this->supplierType = SupplierType::factory()->create();
    $this->country = Country::factory()->create();
    $this->currency = Currency::factory()->create();
    $this->paymentTerm = SupplierPaymentTerm::factory()->create();
});

describe('Suppliers API', function () {
    it('can list suppliers', function () {
        Supplier::factory()->count(3)->create([
            'supplier_type_id' => $this->supplierType->id,
            'country_id' => $this->country->id,
            'currency_id' => $this->currency->id,
        ]);

        $response = $this->getJson(route('setups.suppliers.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'name',
                        'supplier_type_id',
                        'supplier_type' => [
                            'id',
                            'name',
                        ],
                        'country_id',
                        'country' => [
                            'id',
                            'name',
                            'code',
                        ],
                        'opening_balance',
                        'balance',
                        'address',
                        'phone',
                        'mobile',
                        'email',
                        'currency_id',
                        'currency' => [
                            'id',
                            'name',
                            'code',
                            'symbol',
                        ],
                        'is_active',
                        'created_by',
                        'updated_by',
                        'created_at',
                        'updated_at',
                    ]
                ],
                'pagination'
            ]);
    });

    it('can create a supplier with minimum required fields', function () {
        $data = [
            'name' => 'Test Supplier',
            'is_active' => true,
        ];

        $response = $this->postJson(route('setups.suppliers.store'), $data);

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

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Test Supplier',
            'is_active' => true,
        ]);

        // Check if code was auto-generated starting from 1000
        $supplier = Supplier::where('name', 'Test Supplier')->first();
        expect((int)$supplier->code)->toBeGreaterThanOrEqual(1000);
    });

    it('can create a supplier with all fields', function () {
        $data = [
            'name' => 'Complete Supplier',
            'supplier_type_id' => $this->supplierType->id,
            'country_id' => $this->country->id,
            'opening_balance' => 5000.50,
            'address' => '123 Main Street, City',
            'phone' => '05-123123',
            'mobile' => '05/456789',
            'url' => 'https://example.com',
            'email' => 'supplier@example.com',
            'contact_person' => 'John Doe',
            'contact_person_email' => 'john@example.com',
            'contact_person_mobile' => '05 789123',
            'payment_term_id' => $this->paymentTerm->id,
            'ship_from' => 'Shanghai Port',
            'bank_info' => 'Bank Account: 123456789',
            'discount_percentage' => 5.5,
            'currency_id' => $this->currency->id,
            'notes' => 'Important supplier notes',
            'attachments' => ['contract.pdf', 'license.jpg'],
            'is_active' => true,
        ];

        $response = $this->postJson(route('setups.suppliers.store'), $data);

        $response->assertCreated()
            ->assertJson([
                'data' => [
                    'name' => 'Complete Supplier',
                    'opening_balance' => 5000.50,
                    'email' => 'supplier@example.com',
                    'discount_percentage' => 5.5,
                ]
            ]);

        // Verify code was auto-generated
        $supplier = Supplier::where('name', 'Complete Supplier')->first();
        expect($supplier->code)->not()->toBeNull();
        expect((int)$supplier->code)->toBeGreaterThanOrEqual(1000);

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Complete Supplier',
            'email' => 'supplier@example.com',
        ]);
    });

    it('can show a supplier', function () {
        $supplier = Supplier::factory()->create([
            'supplier_type_id' => $this->supplierType->id,
            'country_id' => $this->country->id,
            'currency_id' => $this->currency->id,
        ]);

        $response = $this->getJson(route('setups.suppliers.show', $supplier));

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $supplier->id,
                    'code' => $supplier->code,
                    'name' => $supplier->name,
                ]
            ]);
    });

    it('can update a supplier', function () {
        $supplier = Supplier::factory()->create([
            'supplier_type_id' => $this->supplierType->id,
        ]);
        
        $originalCode = $supplier->code; // Store original code
        
        $data = [
            'name' => 'Updated Supplier',
            'email' => 'updated@example.com',
            'opening_balance' => 1000.00,
        ];

        $response = $this->putJson(route('setups.suppliers.update', $supplier), $data);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'code' => $originalCode, // Code should remain unchanged
                    'name' => 'Updated Supplier',
                    'email' => 'updated@example.com',
                    'opening_balance' => 1000.00,
                ]
            ]);

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'code' => $originalCode, // Verify code wasn't changed
            'name' => 'Updated Supplier',
            'email' => 'updated@example.com',
        ]);
    });

    it('can delete a supplier', function () {
        $supplier = Supplier::factory()->create();

        $response = $this->deleteJson(route('setups.suppliers.destroy', $supplier));

        $response->assertNoContent();
        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
    });

    it('validates required fields when creating', function () {
        $response = $this->postJson(route('setups.suppliers.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('auto-generates code when not provided', function () {
        $data = [
            'name' => 'Auto Code Supplier',
        ];

        $response = $this->postJson(route('setups.suppliers.store'), $data);

        $response->assertCreated();
        
        $supplier = Supplier::where('name', 'Auto Code Supplier')->first();
        expect($supplier->code)->not()->toBeNull();
        expect((int)$supplier->code)->toBeGreaterThanOrEqual(1000);
    });

    it('ignores provided code and always generates new one', function () {
        // Even if user somehow sends a code, it should be ignored
        $data = [
            'name' => 'Custom Code Supplier',
            'code' => '5001', // This should be ignored
        ];

        $response = $this->postJson(route('setups.suppliers.store'), $data);

        $response->assertCreated();
        
        $supplier = Supplier::where('name', 'Custom Code Supplier')->first();
        expect($supplier->code)->not()->toBe('5001'); // Should not use provided code
        expect((int)$supplier->code)->toBeGreaterThanOrEqual(1000); // Should be auto-generated
    });

    it('validates foreign key references', function () {
        $response = $this->postJson(route('setups.suppliers.store'), [
            'name' => 'Test Supplier',
            'supplier_type_id' => 99999, // Non-existent ID
            'country_id' => 99999,
            'currency_id' => 99999,
            'payment_term_id' => 99999,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'supplier_type_id',
                'country_id',
                'currency_id',
                'payment_term_id'
            ]);
    });

    it('code cannot be updated once set', function () {
        $supplier = Supplier::factory()->create();
        $originalCode = $supplier->code;
        
        // Try to update with a code field (should be ignored)
        $data = [
            'name' => 'Updated Supplier',
            'code' => '9999', // This should be ignored
        ];

        $response = $this->putJson(route('setups.suppliers.update', $supplier), $data);

        $response->assertOk();
        
        // Verify code wasn't changed
        $updatedSupplier = $supplier->fresh();
        expect($updatedSupplier->code)->toBe($originalCode);
        expect($updatedSupplier->code)->not()->toBe('9999');
    });

    it('validates email format', function () {
        $response = $this->postJson(route('setups.suppliers.store'), [
            'name' => 'Test Supplier',
            'email' => 'invalid-email',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('validates URL format', function () {
        $response = $this->postJson(route('setups.suppliers.store'), [
            'name' => 'Test Supplier',
            'url' => 'not-a-valid-url',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['url']);
    });

    it('validates numeric fields', function () {
        $response = $this->postJson(route('setups.suppliers.store'), [
            'name' => 'Test Supplier',
            'opening_balance' => 'not-a-number',
            'discount_percentage' => 'invalid',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['opening_balance', 'discount_percentage']);
    });

    it('validates discount percentage range', function () {
        $response = $this->postJson(route('setups.suppliers.store'), [
            'name' => 'Test Supplier',
            'discount_percentage' => 150, // Over 100%
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['discount_percentage']);
    });

    it('can search suppliers by name', function () {
        Supplier::factory()->create(['name' => 'Searchable Supplier']);
        Supplier::factory()->create(['name' => 'Another Supplier']);

        $response = $this->getJson(route('setups.suppliers.index', ['search' => 'Searchable']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['name'])->toBe('Searchable Supplier');
    });

    it('can search suppliers by code', function () {
        Supplier::factory()->create(['code' => '1001', 'name' => 'First Supplier']);
        Supplier::factory()->create(['code' => '1002', 'name' => 'Second Supplier']);

        $response = $this->getJson(route('setups.suppliers.index', ['search' => '1001']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['code'])->toBe('1001');
    });

    it('can filter by active status', function () {
        Supplier::factory()->create(['is_active' => true]);
        Supplier::factory()->create(['is_active' => false]);

        $response = $this->getJson(route('setups.suppliers.index', ['is_active' => true]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['is_active'])->toBe(true);
    });

    it('can filter by supplier type', function () {
        $factoryType = SupplierType::factory()->create(['name' => 'Factory']);
        $wholesaleType = SupplierType::factory()->create(['name' => 'Wholesale']);
        
        Supplier::factory()->create(['supplier_type_id' => $factoryType->id]);
        Supplier::factory()->create(['supplier_type_id' => $wholesaleType->id]);

        $response = $this->getJson(route('setups.suppliers.index', ['supplier_type_id' => $factoryType->id]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['supplier_type_id'])->toBe($factoryType->id);
    });

    it('can filter by country', function () {
        $china = Country::factory()->create(['name' => 'China']);
        $italy = Country::factory()->create(['name' => 'Italy']);
        
        Supplier::factory()->create(['country_id' => $china->id]);
        Supplier::factory()->create(['country_id' => $italy->id]);

        $response = $this->getJson(route('setups.suppliers.index', ['country_id' => $china->id]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['country_id'])->toBe($china->id);
    });

    it('can filter by balance range', function () {
        Supplier::factory()->create(['opening_balance' => 1000]);
        Supplier::factory()->create(['opening_balance' => 5000]);
        Supplier::factory()->create(['opening_balance' => 10000]);

        $response = $this->getJson(route('setups.suppliers.index', [
            'min_balance' => 2000,
            'max_balance' => 8000
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['opening_balance'])->toBe('5000.00');
    });

    it('can sort suppliers by name', function () {
        Supplier::factory()->create(['name' => 'Z Supplier']);
        Supplier::factory()->create(['name' => 'A Supplier']);

        $response = $this->getJson(route('setups.suppliers.index', [
            'sort_by' => 'name',
            'sort_direction' => 'asc'
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data[0]['name'])->toBe('A Supplier');
        expect($data[1]['name'])->toBe('Z Supplier');
    });

    it('can sort suppliers by opening balance', function () {
        Supplier::factory()->create(['opening_balance' => 5000, 'name' => 'High Balance']);
        Supplier::factory()->create(['opening_balance' => 1000, 'name' => 'Low Balance']);

        $response = $this->getJson(route('setups.suppliers.index', [
            'sort_by' => 'opening_balance',
            'sort_direction' => 'desc'
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data[0]['opening_balance'])->toBe('5000.00');
        expect($data[1]['opening_balance'])->toBe('1000.00');
    });

    it('returns 404 for non-existent supplier', function () {
        $response = $this->getJson(route('setups.suppliers.show', 999));

        $response->assertNotFound();
    });

    it('can list trashed suppliers', function () {
        $supplier = Supplier::factory()->create([
            'supplier_type_id' => $this->supplierType->id,
            'country_id' => $this->country->id,
        ]);
        $supplier->delete();

        $response = $this->getJson(route('setups.suppliers.trashed'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'name',
                        'supplier_type',
                        'country',
                        'is_active',
                    ]
                ],
                'pagination'
            ]);

        expect($response->json('data'))->toHaveCount(1);
    });

    it('can restore a trashed supplier', function () {
        $supplier = Supplier::factory()->create();
        $supplier->delete();

        $response = $this->patchJson(route('setups.suppliers.restore', $supplier->id));

        $response->assertOk();
        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'deleted_at' => null,
        ]);
    });

    it('can force delete a trashed supplier', function () {
        $supplier = Supplier::factory()->create();
        $supplier->delete();

        $response = $this->deleteJson(route('setups.suppliers.force-delete', $supplier->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    });

    it('returns 404 when trying to restore non-existent trashed supplier', function () {
        $response = $this->patchJson(route('setups.suppliers.restore', 999));

        $response->assertNotFound();
    });

    it('returns 404 when trying to force delete non-existent trashed supplier', function () {
        $response = $this->deleteJson(route('setups.suppliers.force-delete', 999));

        $response->assertNotFound();
    });

    it('can paginate suppliers', function () {
        Supplier::factory()->count(7)->create();

        $response = $this->getJson(route('setups.suppliers.index', ['per_page' => 3]));

        $response->assertOk();
        $data = $response->json('data');
        $pagination = $response->json('pagination');
        
        expect($data)->toHaveCount(3);
        expect($pagination['total'])->toBe(7);
        expect($pagination['per_page'])->toBe(3);
        expect($pagination['last_page'])->toBe(3);
    });

    it('generates sequential supplier codes via API', function () {
        // Clear existing suppliers
        Supplier::withTrashed()->forceDelete();

        // First API request
        $response1 = $this->postJson(route('setups.suppliers.store'), [
            'name' => 'First Supplier',
        ]);
        $response1->assertCreated();
        $code1 = (int) $response1->json('data.code');

        // Second API request
        $response2 = $this->postJson(route('setups.suppliers.store'), [
            'name' => 'Second Supplier',
        ]);
        $response2->assertCreated();
        $code2 = (int) $response2->json('data.code');

        expect($code1)->toBeGreaterThanOrEqual(1000);
        expect($code2)->toBe($code1 + 1); // strictly sequential
    });

    it('handles concurrent supplier creation with code generation', function () {
        // Simulate concurrent requests by creating multiple suppliers
        $suppliers = [];
        for ($i = 0; $i < 5; $i++) {
            $suppliers[] = Supplier::factory()->create(['name' => "Supplier {$i}"]);
        }

        $codes = collect($suppliers)->map(fn($s) => (int) $s->code)->sort()->values();
        
        // Ensure all codes are unique and sequential
        for ($i = 1; $i < count($codes); $i++) {
            expect($codes[$i])->toBeGreaterThan($codes[$i - 1]);
        }
    });

    it('validates maximum length for string fields', function () {
        $response = $this->postJson(route('setups.suppliers.store'), [
            'name' => str_repeat('a', 256), // Exceeds 255 character limit
            'phone' => str_repeat('1', 21), // Exceeds 20 character limit
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'phone']);
    });

    it('accepts valid phone number formats', function () {
        $validPhoneFormats = [
            '05123123',
            '05-123123',
            '05/123123',
            '05 123 123',
        ];

        foreach ($validPhoneFormats as $index => $phone) {
            $response = $this->postJson(route('setups.suppliers.store'), [
                'name' => "Test Supplier {$index}",
                'phone' => $phone,
            ]);

            $response->assertCreated();
            
            // Verify the phone number was saved correctly
            $this->assertDatabaseHas('suppliers', [
                'name' => "Test Supplier {$index}",
                'phone' => $phone,
            ]);
        }
    });

    it('sets created_by and updated_by fields automatically', function () {
        $supplier = Supplier::factory()->create(['name' => 'Test Supplier']);

        expect($supplier->created_by)->toBe($this->user->id);
        expect($supplier->updated_by)->toBe($this->user->id);

        // Test update tracking
        $supplier->update(['name' => 'Updated Supplier']);
        expect($supplier->fresh()->updated_by)->toBe($this->user->id);
    });
});