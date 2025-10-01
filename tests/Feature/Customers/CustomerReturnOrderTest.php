<?php

use App\Models\Customers\Customer;
use App\Models\Customers\CustomerReturn;
use App\Models\Items\Item;
use App\Models\Setting;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\Warehouse;
use App\Models\User;

uses()->group('api', 'customers', 'customer-return-orders');

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
    ]);
    $this->currency = Currency::factory()->create(['code' => 'USD', 'name' => 'US Dollar']);
    $this->warehouse = Warehouse::factory()->create(['name' => 'Main Warehouse', 'is_active' => true]);
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
            'currency_rate' => 1,
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
                    'ttc_price' => 11,
                    'total_price' => 110,
                    'discount_amount' => 0,
                    'total_price_usd' => 150,
                    'total_volume_cbm' => 1.5,
                    'total_weight_kg' => 25.0,
                    'note' => 'Item return note',
                ]
            ]
        ], $overrides);
    };

    // Helper method to create pending return via factory
    $this->createPendingReturnViaFactory = function ($overrides = []) {
        $baseData = [
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'warehouse_id' => $this->warehouse->id,
            'salesperson_id' => $this->salesmanUser->id,
            'approved_by' => null,
            'approved_at' => null,
        ];

        return CustomerReturn::factory()->create(array_merge($baseData, $overrides));
    };

    // Helper method to create approved return via factory
    $this->createApprovedReturnViaFactory = function ($overrides = []) {
        $baseData = [
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'warehouse_id' => $this->warehouse->id,
            'salesperson_id' => $this->salesmanUser->id,
            'approved_by' => $this->adminUser->id,
            'approved_at' => now(),
        ];

        return CustomerReturn::factory()->create(array_merge($baseData, $overrides));
    };
});

describe('Customer Return Orders API', function () {
    it('can list pending return orders only', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        // Create mix of pending and approved returns
        for ($i = 0; $i < 2; $i++) {
            ($this->createPendingReturnViaFactory)();
        }
        for ($i = 0; $i < 3; $i++) {
            ($this->createApprovedReturnViaFactory)();
        }

        $response = $this->getJson(route('customers.return-orders.index'));

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
                    ]
                ],
                'pagination'
            ]);

        // Should only show pending returns (return orders controller filters pending)
        expect($response->json('data'))->toHaveCount(2);
        foreach ($response->json('data') as $return) {
            expect($return['is_pending'])->toBe(true);
        }
    });

    it('salesman can create return order', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $returnData = ($this->getBaseReturnData)();

        $response = $this->postJson(route('customers.return-orders.store'), $returnData);

        $response->assertCreated()
            ->assertJson([
                'data' => [
                    'status' => 'pending',
                    'is_pending' => true,
                    'is_approved' => false,
                ]
            ]);

        $return = CustomerReturn::latest()->first();
        expect($return->isPending())->toBe(true);
        expect($return->approved_by)->toBeNull();
        expect($return->salesperson_id)->toBe($this->salesmanUser->id);
    });

    it('auto-generates return codes when not provided', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $returnData = ($this->getBaseReturnData)();

        $response = $this->postJson(route('customers.return-orders.store'), $returnData);

        $response->assertCreated();

        $return = CustomerReturn::latest()->first();
        expect($return->code)->not()->toBeNull();
        expect($return->code)->toMatch('/^\d{6}$/'); // 6-digit padded number
        expect($return->return_code)->toBe($return->prefix . $return->code);
    });

    it('can show return order with all relationships', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $return = ($this->createPendingReturnViaFactory)();

        $response = $this->getJson(route('customers.return-orders.show', $return));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'return_code',
                    'customer' => ['id', 'name', 'code'],
                    'currency' => ['id', 'name', 'code', 'symbol'],
                    'warehouse' => ['id', 'name', 'address'],
                    'salesperson' => ['id', 'name'],
                    'created_by_user' => ['id', 'name'],
                    'updated_by_user' => ['id', 'name'],
                ]
            ]);
    });

    it('can update pending return order', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $return = ($this->createPendingReturnViaFactory)();

        $updateData = ($this->getBaseReturnData)([
            'total' => 2000.00,
            'total_usd' => 1600.00,
            'note' => 'Updated return note',
        ]);

        $response = $this->putJson(route('customers.return-orders.update', $return), $updateData);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'total' => '2000.00',
                    'total_usd' => '1600.00',
                    'note' => 'Updated return note',
                ]
            ]);
    });

    it('cannot update approved return order', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $return = ($this->createApprovedReturnViaFactory)();

        $updateData = ($this->getBaseReturnData)([
            'total' => 2000.00,
            'note' => 'Trying to update approved return',
        ]);

        $response = $this->putJson(route('customers.return-orders.update', $return), $updateData);

        $response->assertUnprocessable()
            ->assertJson([
                'message' => 'Cannot update approved returns'
            ]);
    });

    it('can delete pending return order', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $return = ($this->createPendingReturnViaFactory)();

        $response = $this->deleteJson(route('customers.return-orders.destroy', $return));

        $response->assertNoContent();
        $this->assertSoftDeleted('customer_returns', ['id' => $return->id]);
    });

    it('cannot delete approved return order', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $return = ($this->createApprovedReturnViaFactory)();

        $response = $this->deleteJson(route('customers.return-orders.destroy', $return));

        $response->assertUnprocessable()
            ->assertJson([
                'message' => 'Cannot delete approved returns'
            ]);
    });

    it('admin can approve return order', function () {
        $this->actingAs($this->adminUser, 'sanctum');

        $return = ($this->createPendingReturnViaFactory)();

        $approveData = [
            'approve_note' => 'Approved by admin'
        ];

        $response = $this->patchJson(route('customers.return-orders.approve', $return), $approveData);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'status' => 'approved',
                    'is_approved' => true,
                    'is_pending' => false,
                ]
            ]);

        $return->refresh();
        expect($return->isApproved())->toBe(true);
        expect($return->approved_by)->toBe($this->adminUser->id);
        expect($return->approve_note)->toBe('Approved by admin');
    });

    it('salesman cannot approve return orders', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $return = ($this->createPendingReturnViaFactory)();

        $approveData = [
            'approve_note' => 'Trying to approve as salesman'
        ];

        $response = $this->patchJson(route('customers.return-orders.approve', $return), $approveData);

        $response->assertForbidden()
            ->assertJson([
                'message' => 'You do not have permission to approve returns'
            ]);
    });

    it('validates required fields when creating', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $invalidData = [
            'customer_id' => null,
            'currency_id' => null,
            'total' => -100, // Negative amount
            'items' => [], // Empty items array
        ];

        $response = $this->postJson(route('customers.return-orders.store'), $invalidData);

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
        $this->actingAs($this->salesmanUser, 'sanctum');

        $returnData = ($this->getBaseReturnData)([
            'prefix' => 'INVALID'
        ]);

        $response = $this->postJson(route('customers.return-orders.store'), $returnData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['prefix']);
    });

    it('validates customer belongs to salesperson', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

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
            'customer_id' => $otherCustomer->id
        ]);

        $response = $this->postJson(route('customers.return-orders.store'), $returnData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    });

    it('validates active customer requirement', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $inactiveCustomer = Customer::factory()->create([
            'is_active' => false,
            'salesperson_id' => $this->salesmanUser->id,
        ]);

        $returnData = ($this->getBaseReturnData)([
            'customer_id' => $inactiveCustomer->id
        ]);

        $response = $this->postJson(route('customers.return-orders.store'), $returnData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    });

    it('validates active warehouse requirement', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $inactiveWarehouse = Warehouse::factory()->create(['is_active' => false]);

        $returnData = ($this->getBaseReturnData)([
            'warehouse_id' => $inactiveWarehouse->id
        ]);

        $response = $this->postJson(route('customers.return-orders.store'), $returnData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['warehouse_id']);
    });

    it('validates active items requirement', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $inactiveItem = Item::factory()->create([
            'is_active' => false,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

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

        $response = $this->postJson(route('customers.return-orders.store'), $returnData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.item_id']);
    });

    it('salesman can only see their own return orders', function () {
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
            ($this->createPendingReturnViaFactory)();
        }

        // Create returns for other salesman
        for ($i = 0; $i < 3; $i++) {
            ($this->createPendingReturnViaFactory)([
                'customer_id' => $otherCustomer->id,
                'salesperson_id' => $otherSalesman->id
            ]);
        }

        $response = $this->getJson(route('customers.return-orders.index'));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(2);

        foreach ($response->json('data') as $return) {
            expect($return['salesperson']['id'])->toBe($this->salesmanUser->id);
        }
    });

    it('can filter return orders by customer', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $otherCustomer = Customer::factory()->create([
            'salesperson_id' => $this->salesmanUser->id,
            'is_active' => true
        ]);

        for ($i = 0; $i < 2; $i++) {
            ($this->createPendingReturnViaFactory)(['customer_id' => $this->customer->id]);
        }
        for ($i = 0; $i < 3; $i++) {
            ($this->createPendingReturnViaFactory)(['customer_id' => $otherCustomer->id]);
        }

        $response = $this->getJson(route('customers.return-orders.index', ['customer_id' => $this->customer->id]));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(2);
    });

    it('can search return orders by code', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $return1 = ($this->createPendingReturnViaFactory)(['code' => '001001']);
        $return2 = ($this->createPendingReturnViaFactory)(['code' => '001002']);

        $response = $this->getJson(route('customers.return-orders.index', ['search' => '001001']));

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->toHaveCount(1);
        expect($data[0]['code'])->toBe('001001');
    });

    it('sets created_by and updated_by fields automatically', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $returnData = ($this->getBaseReturnData)();

        $response = $this->postJson(route('customers.return-orders.store'), $returnData);

        $response->assertCreated();

        $return = CustomerReturn::latest()->first();
        expect($return->created_by)->toBe($this->salesmanUser->id);
        expect($return->updated_by)->toBe($this->salesmanUser->id);
    });

    it('generates sequential return codes', function () {
        // Clear existing returns and reset counter
        CustomerReturn::withTrashed()->forceDelete();
        Setting::where('group_name', 'customer_returns')
               ->where('key_name', 'code_counter')
               ->update(['value' => '999']);

        $this->actingAs($this->salesmanUser, 'sanctum');

        $return1Data = ($this->getBaseReturnData)();
        $return1Data['items'][0]['item_code'] = 'ITEM001-1';
        $return1 = CustomerReturn::create(array_merge($return1Data, [
            'created_by' => $this->salesmanUser->id,
            'updated_by' => $this->salesmanUser->id,
        ]));
        $code1 = (int) $return1->code;

        $return2Data = ($this->getBaseReturnData)();
        $return2Data['items'][0]['item_code'] = 'ITEM001-2';
        $return2 = CustomerReturn::create(array_merge($return2Data, [
            'created_by' => $this->salesmanUser->id,
            'updated_by' => $this->salesmanUser->id,
        ]));
        $code2 = (int) $return2->code;

        expect($code1)->toBeGreaterThanOrEqual(1000);
        expect($code2)->toBe($code1 + 1);
    });
});

describe('Customer Return Orders Statistics', function () {
    it('can get return order statistics', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        for ($i = 0; $i < 3; $i++) {
            ($this->createPendingReturnViaFactory)();
        }
        for ($i = 0; $i < 2; $i++) {
            ($this->createApprovedReturnViaFactory)();
        }

        $trashedReturn = ($this->createPendingReturnViaFactory)();
        $trashedReturn->delete();

        $response = $this->getJson(route('customers.return-orders.stats'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'total_returns',
                    'pending_returns',
                    'approved_returns',
                    'received_returns',
                    'trashed_returns',
                    'total_amount',
                    'total_amount_usd',
                    'returns_by_prefix',
                    'returns_by_currency',
                    'recent_approved',
                ]
            ]);

        $stats = $response->json('data');
        expect($stats['total_returns'])->toBe(5);
        expect($stats['pending_returns'])->toBe(3);
        expect($stats['approved_returns'])->toBe(2);
        expect($stats['trashed_returns'])->toBe(1);
    });
});

describe('Customer Return Orders Soft Delete and Recovery', function () {
    it('can list trashed return orders', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $return = ($this->createPendingReturnViaFactory)();
        $return->delete();

        $response = $this->getJson(route('customers.return-orders.trashed'));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('admin can restore trashed return order', function () {
        $this->actingAs($this->adminUser, 'sanctum');

        $return = ($this->createPendingReturnViaFactory)();
        $return->delete();

        $response = $this->patchJson(route('customers.return-orders.restore', $return->id));

        $response->assertOk();
        $this->assertDatabaseHas('customer_returns', [
            'id' => $return->id,
            'deleted_at' => null
        ]);
    });

    it('admin can force delete return order', function () {
        $this->actingAs($this->adminUser, 'sanctum');

        $return = ($this->createPendingReturnViaFactory)();
        $return->delete();

        $response = $this->deleteJson(route('customers.return-orders.force-delete', $return->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('customer_returns', ['id' => $return->id]);
    });
});