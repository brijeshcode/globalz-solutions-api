<?php

use App\Models\Customers\Customer;
use App\Models\Customers\CustomerReturn;
use App\Models\Items\Item;
use App\Models\Setting;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\Warehouse;
use App\Models\User;

uses()->group('api', 'customers', 'customer-returns');

beforeEach(function () {
    $this->adminUser = User::factory()->create(['role' => 'admin']);
    $this->salesmanUser = User::factory()->create(['role' => 'salesman']);

    // Create corresponding employee records for the users with matching IDs
    $this->salesmanEmployee = \App\Models\Employees\Employee::factory()->create([
        'id' => $this->salesmanUser->id,
        'user_id' => $this->salesmanUser->id,
        'is_active' => true,
    ]);

    // Create return code counter setting (starting from 1000)
    Setting::create([
        'group_name' => 'customer_returns',
        'key_name' => 'code_counter',
        'value' => '999',
        'data_type' => 'number',
        'description' => 'Customer return code counter starting from 1000'
    ]);

    // Create related models for testing
    $this->customer = Customer::factory()->create([
        'name' => 'Test Customer',
        'salesperson_id' => $this->salesmanUser->id, // Now matches Employee ID
        'created_by' => $this->adminUser->id,
        'updated_by' => $this->adminUser->id,
        'is_active' => true,
        'address' => '123 Test Street',
        'city' => 'Test City',
        'mobile' => '+1234567890',
        'mof_tax_number' => '12345678901',
    ]);
    $this->currency = Currency::factory()->create(['code' => 'USD', 'name' => 'US Dollar']);
    $this->warehouse = Warehouse::factory()->create([
        'name' => 'Main Warehouse',
        'is_active' => true,
        'address_line_1' => '123 Warehouse Street'
    ]);
    $this->item = Item::factory()->create([
        'short_name' => 'Test Item',
        'code' => 'ITEM001',
        'is_active' => true,
        'created_by' => $this->adminUser->id,
        'updated_by' => $this->adminUser->id,
    ]);

    // Helper method for base return data
    $this->getBaseReturnData = function ($overrides = []) {
        return array_merge([
            'date' => '2025-01-15',
            'prefix' => 'RTX',
            'customer_id' => $this->customer->id,
            'salesperson_id' => $this->salesmanUser->id,
            'currency_id' => $this->currency->id,
            'warehouse_id' => $this->warehouse->id,
            'total' => 1000.00,
            'total_usd' => 800.00,
            'total_volume_cbm' => 1.5,
            'total_weight_kg' => 25.0,
            'note' => 'Test return note',
            'items' => [
                [
                    'item_code' => 'ITEM001',
                    'item_id' => $this->item->id,
                    'quantity' => 10,
                    'price' => 100.00,
                    'discount_percent' => 5.0,
                    'unit_discount_amount' => 5.00,
                    'tax_percent' => 10.0,
                    'total_volume_cbm' => 1.5,
                    'total_weight_kg' => 25.0,
                    'note' => 'Item return note',
                ]
            ]
        ], $overrides);
    };

    // Helper method to create approved return via API (controller always creates approved)
    $this->createApprovedReturnViaApi = function ($overrides = []) {
        $this->actingAs($this->adminUser, 'sanctum');

        $returnData = ($this->getBaseReturnData)($overrides);

        $response = $this->postJson(route('customers.returns.store'), $returnData);
        $response->assertCreated();

        return CustomerReturn::latest()->first();
    };

    // Helper method to create return via factory with proper relationships
    $this->createReturnViaFactory = function ($state = null, $overrides = []) {
        $baseData = [
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'warehouse_id' => $this->warehouse->id,
            'salesperson_id' => $this->salesmanUser->id,
        ];

        // Add approval data for approved returns
        if ($state === 'approved') {
            $baseData['approved_by'] = $this->adminUser->id;
            $baseData['approved_at'] = now();
        }

        $factory = CustomerReturn::factory();

        if ($state === 'pending') {
            $baseData['approved_by'] = null;
            $baseData['approved_at'] = null;
        } elseif ($state === 'approved') {
            // Keep the approved data from above
        }

        return $factory->create(array_merge($baseData, $overrides));
    };
});

describe('Customer Returns API', function () {
    it('can list approved customer returns only', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        // Create mix of pending and approved returns
        for ($i = 0; $i < 2; $i++) {
            ($this->createReturnViaFactory)('pending');
        }
        for ($i = 0; $i < 3; $i++) {
            ($this->createReturnViaFactory)('approved');
        }

        $response = $this->getJson(route('customers.returns.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'return_code',
                        'date',
                        'prefix',
                        'total',
                        'total_usd',
                        'status',
                        'is_approved',
                        'is_pending',
                        'customer',
                        'currency',
                        'warehouse',
                        'salesperson',
                        'approved_by_user',
                    ]
                ],
                'pagination'
            ]);

        // Should only show approved returns (controller has ->approved() filter)
        expect($response->json('data'))->toHaveCount(3);
        foreach ($response->json('data') as $return) {
            expect($return['is_approved'])->toBe(true);
        }
    });

    it('admin can create approved return', function () {
        $this->actingAs($this->adminUser, 'sanctum');

        $returnData = ($this->getBaseReturnData)([
            'approve_note' => 'Admin approved during creation'
        ]);

        $response = $this->postJson(route('customers.returns.store'), $returnData);

        $response->assertCreated()
            ->assertJson([
                'data' => [
                    'status' => 'approved',
                    'is_approved' => true,
                    'is_pending' => false,
                ]
            ]);

        $return = CustomerReturn::latest()->first();
        expect($return->isApproved())->toBe(true);
        expect($return->approved_by)->toBe($this->adminUser->id);
        expect($return->approve_note)->toBe('Admin approved during creation');
    });

    it('non-admin cannot create returns', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $returnData = ($this->getBaseReturnData)();

        $response = $this->postJson(route('customers.returns.store'), $returnData);

        $response->assertForbidden();
    });

    it('auto-generates return codes when not provided', function () {
        $this->actingAs($this->adminUser, 'sanctum');

        $returnData = ($this->getBaseReturnData)();

        $response = $this->postJson(route('customers.returns.store'), $returnData);

        $response->assertCreated();

        $return = CustomerReturn::latest()->first();
        expect($return->code)->not()->toBeNull();
        expect($return->code)->toMatch('/^\d{6}$/'); // 6-digit padded number
        expect($return->return_code)->toBe($return->prefix . $return->code);
    });

    it('can show return with all relationships', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $return = ($this->createApprovedReturnViaApi)();

        $response = $this->getJson(route('customers.returns.show', $return));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'return_code',
                    'customer' => ['id', 'name', 'code', 'address', 'city', 'mobile', 'mof_tax_number'],
                    'currency' => ['id', 'name', 'code', 'symbol'],
                    'warehouse' => ['id', 'name', 'address'],
                    'salesperson' => ['id', 'name'],
                    'approved_by_user' => ['id', 'name'],
                    'created_by_user' => ['id', 'name'],
                    'updated_by_user' => ['id', 'name'],
                    'items' => [
                        '*' => [
                            'item' => ['id', 'description', 'code'],
                        ]
                    ]
                ]
            ]);
    });

    it('cannot show non-approved returns', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $return = ($this->createReturnViaFactory)('pending');

        $response = $this->getJson(route('customers.returns.show', $return));

        $response->assertNotFound()
            ->assertJson([
                'message' => 'Return is not approved'
            ]);
    });

    it('salesman can only view their own approved returns', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $otherSalesman = User::factory()->create(['role' => 'salesman']);
        $otherSalesmanEmployee = \App\Models\Employees\Employee::factory()->create([
            'id' => $otherSalesman->id,
            'user_id' => $otherSalesman->id,
            'is_active' => true,
        ]);
        $otherCustomer = Customer::factory()->create(['salesperson_id' => $otherSalesman->id]);

        $return = ($this->createReturnViaFactory)('approved', [
            'customer_id' => $otherCustomer->id,
            'salesperson_id' => $otherSalesman->id
        ]);

        $response = $this->getJson(route('customers.returns.show', $return));

        $response->assertForbidden()
            ->assertJson([
                'message' => 'You can only view your own returns'
            ]);
    });

    it('validates required fields when creating', function () {
        $this->actingAs($this->adminUser, 'sanctum');

        $invalidData = [
            'customer_id' => null,
            'currency_id' => null,
            'total' => -100, // Negative amount
            'items' => [], // Empty items array
        ];

        $response = $this->postJson(route('customers.returns.store'), $invalidData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date',
                'prefix',
                'customer_id',
                'currency_id',
                'warehouse_id',
                'total',
                'total_usd',
                'items',
            ]);
    });

    it('validates prefix values', function () {
        $this->actingAs($this->adminUser, 'sanctum');

        $returnData = ($this->getBaseReturnData)([
            'prefix' => 'INVALID'
        ]);

        $response = $this->postJson(route('customers.returns.store'), $returnData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['prefix']);
    });

    it('validates active customer requirement', function () {
        $this->actingAs($this->adminUser, 'sanctum');

        $inactiveCustomer = Customer::factory()->create([
            'is_active' => false,
            'salesperson_id' => $this->salesmanUser->id,
        ]);

        $returnData = ($this->getBaseReturnData)([
            'customer_id' => $inactiveCustomer->id
        ]);

        $response = $this->postJson(route('customers.returns.store'), $returnData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    });

    it('validates customer belongs to salesperson when salesperson specified', function () {
        $this->actingAs($this->adminUser, 'sanctum');

        $otherSalesman = User::factory()->create(['role' => 'salesman']);
        $otherSalesmanEmployee = \App\Models\Employees\Employee::factory()->create([
            'id' => $otherSalesman->id,
            'user_id' => $otherSalesman->id,
            'is_active' => true,
        ]);
        $otherCustomer = Customer::factory()->create([
            'salesperson_id' => $otherSalesman->id,
            'is_active' => true
        ]);

        $returnData = ($this->getBaseReturnData)([
            'customer_id' => $otherCustomer->id,
            'salesperson_id' => $this->salesmanUser->id // Different salesperson
        ]);

        $response = $this->postJson(route('customers.returns.store'), $returnData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    });

    it('validates active warehouse requirement', function () {
        $this->actingAs($this->adminUser, 'sanctum');

        $inactiveWarehouse = Warehouse::factory()->create(['is_active' => false]);

        $returnData = ($this->getBaseReturnData)([
            'warehouse_id' => $inactiveWarehouse->id
        ]);

        $response = $this->postJson(route('customers.returns.store'), $returnData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['warehouse_id']);
    });

    it('validates active items requirement', function () {
        $this->actingAs($this->adminUser, 'sanctum');

        $inactiveItem = Item::factory()->create(['is_active' => false]);

        $returnData = ($this->getBaseReturnData)([
            'items' => [
                [
                    'item_code' => 'INACTIVE001',
                    'item_id' => $inactiveItem->id,
                    'quantity' => 10,
                    'price' => 100.00,
                ]
            ]
        ]);

        $response = $this->postJson(route('customers.returns.store'), $returnData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.item_id']);
    });

    it('salesman can only see their own approved returns in listing', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $otherSalesman = User::factory()->create(['role' => 'salesman']);
        $otherSalesmanEmployee = \App\Models\Employees\Employee::factory()->create([
            'id' => $otherSalesman->id,
            'user_id' => $otherSalesman->id,
            'is_active' => true,
        ]);
        $otherCustomer = Customer::factory()->create(['salesperson_id' => $otherSalesman->id]);

        // Create returns for current salesman
        for ($i = 0; $i < 2; $i++) {
            ($this->createReturnViaFactory)('approved');
        }

        // Create returns for other salesman
        for ($i = 0; $i < 3; $i++) {
            ($this->createReturnViaFactory)('approved', [
                'customer_id' => $otherCustomer->id,
                'salesperson_id' => $otherSalesman->id
            ]);
        }

        $response = $this->getJson(route('customers.returns.index'));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(2);

        foreach ($response->json('data') as $return) {
            expect($return['salesperson']['id'])->toBe($this->salesmanUser->id);
        }
    });

    it('can filter returns by customer', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $otherCustomer = Customer::factory()->create([
            'salesperson_id' => $this->salesmanUser->id,
            'is_active' => true
        ]);

        for ($i = 0; $i < 2; $i++) {
            ($this->createReturnViaFactory)('approved', ['customer_id' => $this->customer->id]);
        }
        for ($i = 0; $i < 3; $i++) {
            ($this->createReturnViaFactory)('approved', ['customer_id' => $otherCustomer->id]);
        }

        $response = $this->getJson(route('customers.returns.index', ['customer_id' => $this->customer->id]));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(2);
    });

    it('can filter returns by currency', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $otherCurrency = Currency::factory()->create(['code' => 'EUR']);

        for ($i = 0; $i < 2; $i++) {
            ($this->createReturnViaFactory)('approved', ['currency_id' => $this->currency->id]);
        }
        for ($i = 0; $i < 3; $i++) {
            ($this->createReturnViaFactory)('approved', ['currency_id' => $otherCurrency->id]);
        }

        $response = $this->getJson(route('customers.returns.index', ['currency_id' => $this->currency->id]));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(2);
    });

    it('can filter returns by warehouse', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $otherWarehouse = Warehouse::factory()->create(['is_active' => true]);

        for ($i = 0; $i < 2; $i++) {
            ($this->createReturnViaFactory)('approved', ['warehouse_id' => $this->warehouse->id]);
        }
        for ($i = 0; $i < 3; $i++) {
            ($this->createReturnViaFactory)('approved', ['warehouse_id' => $otherWarehouse->id]);
        }

        $response = $this->getJson(route('customers.returns.index', ['warehouse_id' => $this->warehouse->id]));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(2);
    });

    it('can filter returns by date range', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        ($this->createReturnViaFactory)('approved', ['date' => '2025-01-01']);
        ($this->createReturnViaFactory)('approved', ['date' => '2025-02-15']);
        ($this->createReturnViaFactory)('approved', ['date' => '2025-03-30']);

        $response = $this->getJson(route('customers.returns.index', [
            'start_date' => '2025-02-01',
            'end_date' => '2025-02-28'
        ]));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
    });

    it('can filter returns by prefix', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        for ($i = 0; $i < 2; $i++) {
            ($this->createReturnViaFactory)('approved', ['prefix' => 'RTX']);
        }
        for ($i = 0; $i < 3; $i++) {
            ($this->createReturnViaFactory)('approved', ['prefix' => 'RTV']);
        }

        $response = $this->getJson(route('customers.returns.index', ['prefix' => 'RTX']));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(2);
    });

    it('can filter returns by status (received/not_received)', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        // Create received returns
        for ($i = 0; $i < 2; $i++) {
            ($this->createReturnViaFactory)('approved', [
                'return_received_by' => $this->adminUser->id,
                'return_received_at' => now()
            ]);
        }

        // Create not received returns
        for ($i = 0; $i < 3; $i++) {
            ($this->createReturnViaFactory)('approved', [
                'return_received_by' => null,
                'return_received_at' => null
            ]);
        }

        // Test received filter
        $response = $this->getJson(route('customers.returns.index', ['status' => 'received']));
        $response->assertOk();
        expect($response->json('data'))->toHaveCount(2);

        // Test not_received filter
        $response = $this->getJson(route('customers.returns.index', ['status' => 'not_received']));
        $response->assertOk();
        expect($response->json('data'))->toHaveCount(3);
    });

    it('can search returns by code', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $return1 = ($this->createReturnViaFactory)('approved', ['code' => '001001']);
        $return2 = ($this->createReturnViaFactory)('approved', ['code' => '001002']);

        $response = $this->getJson(route('customers.returns.index', ['search' => '001001']));

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->toHaveCount(1);
        expect($data[0]['code'])->toBe('001001');
    });

    it('sets created_by and updated_by fields automatically', function () {
        $return = ($this->createApprovedReturnViaApi)();

        expect($return->created_by)->toBe($this->adminUser->id);
        expect($return->updated_by)->toBe($this->adminUser->id);
    });

    it('returns 404 for non-existent return', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $response = $this->getJson(route('customers.returns.show', 999));

        $response->assertNotFound();
    });

    it('can paginate returns', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        for ($i = 0; $i < 7; $i++) {
            ($this->createReturnViaFactory)('approved');
        }

        $response = $this->getJson(route('customers.returns.index', ['per_page' => 3]));

        $response->assertOk();
        $data = $response->json('data');
        $pagination = $response->json('pagination');

        expect($data)->toHaveCount(3);
        expect($pagination['total'])->toBe(7);
        expect($pagination['per_page'])->toBe(3);
        expect($pagination['last_page'])->toBe(3);
    });

    it('generates sequential return codes', function () {
        // Clear existing returns and reset counter
        CustomerReturn::withTrashed()->forceDelete();
        Setting::where('group_name', 'customer_returns')
               ->where('key_name', 'code_counter')
               ->update(['value' => '999']);

        $this->actingAs($this->adminUser, 'sanctum');

        // Create returns using direct model creation to avoid potential API race conditions
        $baseData = ($this->getBaseReturnData)();
        unset($baseData['items']); // Remove items for direct model creation
        $baseData['approved_by'] = $this->adminUser->id;
        $baseData['approved_at'] = now();
        $baseData['created_by'] = $this->adminUser->id;
        $baseData['updated_by'] = $this->adminUser->id;
        $return1 = CustomerReturn::create($baseData);
        $code1 = (int) $return1->code;

        $baseData2 = ($this->getBaseReturnData)();
        unset($baseData2['items']); // Remove items for direct model creation
        $baseData2['approved_by'] = $this->adminUser->id;
        $baseData2['approved_at'] = now();
        $baseData2['created_by'] = $this->adminUser->id;
        $baseData2['updated_by'] = $this->adminUser->id;
        $return2 = CustomerReturn::create($baseData2);
        $code2 = (int) $return2->code;

        expect($code1)->toBeGreaterThanOrEqual(1000);
        expect($code2)->toBe($code1 + 1); // strictly sequential
    });
});

describe('Customer Returns Soft Delete and Recovery', function () {
    it('can list trashed returns', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $return = ($this->createReturnViaFactory)('approved');
        $return->delete();

        $response = $this->getJson(route('customers.returns.trashed'));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('admin can restore trashed return', function () {
        $this->actingAs($this->adminUser, 'sanctum');

        $return = ($this->createReturnViaFactory)('approved');
        $return->delete();

        $response = $this->patchJson(route('customers.returns.restore', $return->id));

        $response->assertOk();
        $this->assertDatabaseHas('customer_returns', [
            'id' => $return->id,
            'deleted_at' => null
        ]);
    });

    it('employee cannot restore returns', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $return = ($this->createReturnViaFactory)('approved');
        $return->delete();

        $response = $this->patchJson(route('customers.returns.restore', $return->id));

        $response->assertForbidden()
            ->assertJson([
                'message' => 'Only admins can restore returns'
            ]);
    });

    it('can only restore approved returns', function () {
        $this->actingAs($this->adminUser, 'sanctum');

        $return = ($this->createReturnViaFactory)('pending');
        $return->delete();

        $response = $this->patchJson(route('customers.returns.restore', $return->id));

        $response->assertUnprocessable()
            ->assertJson([
                'message' => 'Can only restore approved returns'
            ]);
    });

    it('admin can force delete return', function () {
        $this->actingAs($this->adminUser, 'sanctum');

        $return = ($this->createReturnViaFactory)('approved');
        $return->delete();

        $response = $this->deleteJson(route('customers.returns.force-delete', $return->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('customer_returns', ['id' => $return->id]);
    });

    it('employee cannot force delete returns', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $return = ($this->createReturnViaFactory)('approved');
        $return->delete();

        $response = $this->deleteJson(route('customers.returns.force-delete', $return->id));

        $response->assertForbidden()
            ->assertJson([
                'message' => 'Only admins can permanently delete returns'
            ]);
    });
});

describe('Customer Returns Statistics', function () {
    it('can get return statistics', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        for ($i = 0; $i < 3; $i++) {
            ($this->createReturnViaFactory)('pending');
        }
        for ($i = 0; $i < 2; $i++) {
            ($this->createReturnViaFactory)('approved');
        }

        // Create a received return
        ($this->createReturnViaFactory)('approved', [
            'return_received_by' => $this->adminUser->id,
            'return_received_at' => now()
        ]);

        // Create a trashed return
        $trashedReturn = ($this->createReturnViaFactory)('approved');
        $trashedReturn->delete();

        $response = $this->getJson(route('customers.returns.stats'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'total_returns',
                    'received_returns',
                    'not_received_returns',
                    'trashed_returns',
                    'total_amount',
                    'total_amount_usd',
                    'returns_by_prefix',
                    'returns_by_warehouse',
                    'returns_by_currency',
                    'recent_received',
                ]
            ]);

        $stats = $response->json('data');
        expect($stats['total_returns'])->toBe(3); // Only approved returns counted
        expect($stats['received_returns'])->toBe(1);
        expect($stats['not_received_returns'])->toBe(2);
        expect($stats['trashed_returns'])->toBe(1);
    });

    it('calculates totals from approved returns only', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        // Pending returns (should not be counted in totals)
        for ($i = 0; $i < 2; $i++) {
            ($this->createReturnViaFactory)('pending', ['total' => 1000.00, 'total_usd' => 800.00]);
        }

        // Approved returns (should be counted)
        for ($i = 0; $i < 3; $i++) {
            ($this->createReturnViaFactory)('approved', ['total' => 500.00, 'total_usd' => 400.00]);
        }

        $response = $this->getJson(route('customers.returns.stats'));

        $response->assertOk();
        $stats = $response->json('data');

        // Only approved returns are counted in totals
        expect((float)$stats['total_amount'])->toBe(1500.00); // 3 × 500
        expect((float)$stats['total_amount_usd'])->toBe(1200.00); // 3 × 400
        expect($stats['total_returns'])->toBe(3); // Only approved returns
    });

    it('salesman sees only their own statistics', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $otherSalesman = User::factory()->create(['role' => 'salesman']);
        $otherSalesmanEmployee = \App\Models\Employees\Employee::factory()->create([
            'id' => $otherSalesman->id,
            'user_id' => $otherSalesman->id,
            'is_active' => true,
        ]);
        $otherCustomer = Customer::factory()->create(['salesperson_id' => $otherSalesman->id]);

        // Returns for current salesman
        for ($i = 0; $i < 2; $i++) {
            ($this->createReturnViaFactory)('approved', ['total' => 500.00]);
        }

        // Returns for other salesman
        for ($i = 0; $i < 3; $i++) {
            ($this->createReturnViaFactory)('approved', [
                'customer_id' => $otherCustomer->id,
                'salesperson_id' => $otherSalesman->id,
                'total' => 1000.00
            ]);
        }

        $response = $this->getJson(route('customers.returns.stats'));

        $response->assertOk();
        $stats = $response->json('data');

        // Should only count returns for current salesman
        expect($stats['total_returns'])->toBe(2);
        expect((float)$stats['total_amount'])->toBe(1000.00); // 2 × 500
    });
});