<?php

use App\Models\Customers\Customer;
use App\Models\Customers\Sale;
use App\Models\Inventory\ItemPrice;
use App\Models\Items\Item;
use App\Models\Setting;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\Warehouse;
use App\Models\User;

uses()->group('api', 'customers', 'sale-orders');

beforeEach(function () {
    $this->adminUser = User::factory()->create(['role' => 'admin']);
    $this->salesmanUser = User::factory()->create(['role' => 'salesman']);

    // Create corresponding employee records for the users with matching IDs
    $this->salesmanEmployee = \App\Models\Employees\Employee::factory()->create([
        'id' => $this->salesmanUser->id,
        'user_id' => $this->salesmanUser->id,
        'is_active' => true,
    ]);

    // Create sale code counter setting (starting from 1000)
    Setting::create([
        'group_name' => 'sales',
        'key_name' => 'code_counter',
        'value' => '1000',
        'data_type' => 'number',
        'description' => 'Sale code counter starting from 1000'
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
        'starting_price' => 100.00, // Starting sell price
        'created_by' => $this->adminUser->id,
        'updated_by' => $this->adminUser->id,
    ]);

    // Update item price for profit calculation (automatically created from starting_price)
    $this->item->itemPrice()->update([
        'price_usd' => 50.00, // Cost price for profit calculations
    ]);

    // Create inventory for the item in the test warehouse
    \App\Models\Inventory\Inventory::create([
        'item_id' => $this->item->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 1000.00, // Sufficient quantity for tests
    ]);

    // Helper method for base sale data
    $this->getBaseSaleData = function ($overrides = []) {
        return array_merge([
            'date' => '2025-01-15',
            'prefix' => 'INV',
            'customer_id' => $this->customer->id,
            'salesperson_id' => $this->salesmanEmployee->id,
            'currency_id' => $this->currency->id,
            'warehouse_id' => $this->warehouse->id,
            'client_po_number' => 'PO-001',
            'currency_rate' => 1,
            'credit_limit' => 10000.00,
            'outStanding_balance' => 0.00,
            'sub_total' => 1000.00,
            'sub_total_usd' => 1000.00,
            'discount_amount' => 50.00,
            'discount_amount_usd' => 50.00,
            'total' => 950.00,
            'total_usd' => 950.00,
            'note' => 'Test sale note',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'quantity' => 10,
                    'price' => 100.00,
                    'ttc_price' => 110.00,
                    'tax_percent' => 10.0,
                    'discount_percent' => 5.0,
                    'unit_discount_amount' => 5.00,
                    'discount_amount' => 50.00,
                    'total_price' => 950.00,
                    'total_price_usd' => 950.00,
                    'note' => 'Item sale note',
                ]
            ]
        ], $overrides);
    };

    // Helper method to create pending sale via factory
    $this->createPendingSaleViaFactory = function ($overrides = []) {
        $baseData = [
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'warehouse_id' => $this->warehouse->id,
            'salesperson_id' => $this->salesmanEmployee->id,
            'approved_by' => null,
            'approved_at' => null,
        ];

        return Sale::factory()->create(array_merge($baseData, $overrides));
    };

    // Helper method to create approved sale via factory
    $this->createApprovedSaleViaFactory = function ($overrides = []) {
        $baseData = [
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'warehouse_id' => $this->warehouse->id,
            'salesperson_id' => $this->salesmanEmployee->id,
            'approved_by' => $this->adminUser->id,
            'approved_at' => now(),
        ];

        return Sale::factory()->create(array_merge($baseData, $overrides));
    };
});

describe('Sale Orders API', function () {
    it('can list pending sale orders only', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        // Create mix of pending and approved sales
        for ($i = 0; $i < 2; $i++) {
            ($this->createPendingSaleViaFactory)();
        }
        for ($i = 0; $i < 3; $i++) {
            ($this->createApprovedSaleViaFactory)();
        }

        $response = $this->getJson(route('customers.sale-orders.index'));
        // $response->dump();
        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'sale_code',
                        'date',
                        'prefix',
                        'total',
                        'total_usd',
                        'total_profit',
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

        // Should only show pending sales (sale orders controller filters pending)
        expect($response->json('data'))->toHaveCount(2);
        foreach ($response->json('data') as $sale) {
            expect($sale['is_pending'])->toBe(true);
        }
    });

    it('salesman can create sale order', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $saleData = ($this->getBaseSaleData)();

        $response = $this->postJson(route('customers.sale-orders.store'), $saleData);

        $response->assertCreated()
            ->assertJson([
                'data' => [
                    'is_pending' => true,
                    'is_approved' => false,
                ]
            ]);

        $sale = Sale::latest()->first();
        expect($sale->isPending())->toBe(true);
        expect($sale->approved_by)->toBeNull();
        expect($sale->salesperson_id)->toBe($this->salesmanEmployee->id);
    });

    it('calculates profit correctly on sale order creation', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $saleData = ($this->getBaseSaleData)([
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'quantity' => 10,
                    'price' => 100.00, // Sale price
                    'unit_discount_amount' => 5.00, // Discount per unit
                    'ttc_price' => 104.025,
                    'tax_percent' => 10.0,
                    'discount_percent' => 5.0,
                    'discount_amount' => 50.00,
                    'total_price' => 950.00,
                    'total_price_usd' => 950.00,
                ]
            ]
        ]);

        $response = $this->postJson(route('customers.sale-orders.store'), $saleData);

        $response->assertCreated();

        $sale = Sale::latest()->first();
        // Profit = (Sale Price - Discount) - Cost Price = (100 - 5) - 50 = 45 per unit
        // Total profit = 45 * 10 = 450
        expect((float)$sale->total_profit)->toBe(450.0);
    });

    it('auto-generates sale codes when not provided', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $saleData = ($this->getBaseSaleData)();

        $response = $this->postJson(route('customers.sale-orders.store'), $saleData);

        $response->assertCreated();

        $sale = Sale::latest()->first();
        expect($sale->code)->not()->toBeNull();
        expect($sale->code)->toMatch('/^\d{6}$/'); // 6-digit padded number
        expect($sale->sale_code)->toBe($sale->prefix . $sale->code);
    });

    it('can show sale order with all relationships', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $sale = ($this->createPendingSaleViaFactory)();

        $response = $this->getJson(route('customers.sale-orders.show', $sale));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'sale_code',
                    'customer' => ['id', 'name', 'code'],
                    'currency' => ['id', 'name', 'code', 'symbol'],
                    'warehouse' => ['id', 'name', 'address'],
                    'salesperson' => ['id', 'name'],
                    'created_by_user' => ['id', 'name'],
                    'updated_by_user' => ['id', 'name'],
                ]
            ]);
    });

    it('can update pending sale order', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $sale = ($this->createPendingSaleViaFactory)();

        $updateData = ($this->getBaseSaleData)([
            'total' => 2000.00,
            'total_usd' => 2000.00,
            'note' => 'Updated sale note',
        ]);

        $response = $this->putJson(route('customers.sale-orders.update', $sale), $updateData);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'total' => '2000.00',
                    'total_usd' => '2000.00',
                    'note' => 'Updated sale note',
                ]
            ]);
    });

    it('cannot update approved sale order', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $sale = ($this->createApprovedSaleViaFactory)();

        $updateData = ($this->getBaseSaleData)([
            'total' => 2000.00,
            'note' => 'Trying to update approved sale',
        ]);

        $response = $this->putJson(route('customers.sale-orders.update', $sale), $updateData);

        $response->assertUnprocessable()
            ->assertJson([
                'message' => 'Cannot update approved sales'
            ]);
    });

    it('can delete pending sale order', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $sale = ($this->createPendingSaleViaFactory)();

        $response = $this->deleteJson(route('customers.sale-orders.destroy', $sale));

        $response->assertNoContent();
        $this->assertSoftDeleted('sales', ['id' => $sale->id]);
    });

    it('cannot delete approved sale order', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $sale = ($this->createApprovedSaleViaFactory)();

        $response = $this->deleteJson(route('customers.sale-orders.destroy', $sale));

        $response->assertUnprocessable()
            ->assertJson([
                'message' => 'Cannot delete approved sales'
            ]);
    });

    it('admin can approve sale order', function () {
        $this->actingAs($this->adminUser, 'sanctum');

        $sale = ($this->createPendingSaleViaFactory)();

        $approveData = [
            'approve_note' => 'Approved by admin'
        ];

        $response = $this->patchJson(route('customers.sale-orders.approve', $sale), $approveData);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'is_approved' => true,
                    'is_pending' => false,
                ]
            ]);

        $sale->refresh();
        expect($sale->isApproved())->toBe(true);
        expect($sale->approved_by)->toBe($this->adminUser->id);
        expect($sale->approve_note)->toBe('Approved by admin');
    });

    it('salesman cannot approve sale orders', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $sale = ($this->createPendingSaleViaFactory)();

        $approveData = [
            'approve_note' => 'Trying to approve as salesman'
        ];

        $response = $this->patchJson(route('customers.sale-orders.approve', $sale), $approveData);

        $response->assertForbidden()
            ->assertJson([
                'message' => 'You do not have permission to approve sales'
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

        $response = $this->postJson(route('customers.sale-orders.store'), $invalidData);

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

        $saleData = ($this->getBaseSaleData)([
            'prefix' => 'INVALID'
        ]);

        $response = $this->postJson(route('customers.sale-orders.store'), $saleData);

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
            'salesperson_id' => $otherSalesmanEmployee->id,
            'is_active' => true
        ]);

        $saleData = ($this->getBaseSaleData)([
            'customer_id' => $otherCustomer->id
        ]);

        $response = $this->postJson(route('customers.sale-orders.store'), $saleData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    });

    it('validates active customer requirement', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $inactiveCustomer = Customer::factory()->create([
            'is_active' => false,
            'salesperson_id' => $this->salesmanEmployee->id,
        ]);

        $saleData = ($this->getBaseSaleData)([
            'customer_id' => $inactiveCustomer->id
        ]);

        $response = $this->postJson(route('customers.sale-orders.store'), $saleData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    });

    it('validates active warehouse requirement', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $inactiveWarehouse = Warehouse::factory()->create(['is_active' => false]);

        $saleData = ($this->getBaseSaleData)([
            'warehouse_id' => $inactiveWarehouse->id
        ]);

        $response = $this->postJson(route('customers.sale-orders.store'), $saleData);

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

        $saleData = ($this->getBaseSaleData)([
            'items' => [
                [
                    'item_id' => $inactiveItem->id,
                    'quantity' => 10,
                    'price' => 100.00,
                    'total_price' => 1000.00,
                ]
            ]
        ]);

        $response = $this->postJson(route('customers.sale-orders.store'), $saleData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.item_id']);
    });

    it('salesman can only see their own sale orders', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $otherSalesman = User::factory()->create(['role' => 'salesman']);
        $otherSalesmanEmployee = \App\Models\Employees\Employee::factory()->create([
            'id' => $otherSalesman->id,
            'user_id' => $otherSalesman->id,
            'is_active' => true,
        ]);
        $otherCustomer = Customer::factory()->create(['salesperson_id' => $otherSalesmanEmployee->id]);

        // Create sales for current salesman
        for ($i = 0; $i < 2; $i++) {
            ($this->createPendingSaleViaFactory)();
        }

        // Create sales for other salesman
        for ($i = 0; $i < 3; $i++) {
            ($this->createPendingSaleViaFactory)([
                'customer_id' => $otherCustomer->id,
                'salesperson_id' => $otherSalesmanEmployee->id
            ]);
        }

        $response = $this->getJson(route('customers.sale-orders.index'));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(2);

        foreach ($response->json('data') as $sale) {
            expect($sale['salesperson']['id'])->toBe($this->salesmanEmployee->id);
        }
    });

    it('can filter sale orders by customer', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $otherCustomer = Customer::factory()->create([
            'salesperson_id' => $this->salesmanEmployee->id,
            'is_active' => true
        ]);

        for ($i = 0; $i < 2; $i++) {
            ($this->createPendingSaleViaFactory)(['customer_id' => $this->customer->id]);
        }
        for ($i = 0; $i < 3; $i++) {
            ($this->createPendingSaleViaFactory)(['customer_id' => $otherCustomer->id]);
        }

        $response = $this->getJson(route('customers.sale-orders.index', ['customer_id' => $this->customer->id]));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(2);
    });

    it('can search sale orders by code', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $sale1 = ($this->createPendingSaleViaFactory)(['code' => '001001']);
        $sale2 = ($this->createPendingSaleViaFactory)(['code' => '001002']);

        $response = $this->getJson(route('customers.sale-orders.index', ['search' => '001001']));

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->toHaveCount(1);
        expect($data[0]['code'])->toBe('001001');
    });

    it('sets created_by and updated_by fields automatically', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $saleData = ($this->getBaseSaleData)();

        $response = $this->postJson(route('customers.sale-orders.store'), $saleData);

        $response->assertCreated();

        $sale = Sale::latest()->first();
        expect($sale->created_by)->toBe($this->salesmanUser->id);
        expect($sale->updated_by)->toBe($this->salesmanUser->id);
    });

    it('generates sequential sale codes', function () {
        // Clear existing sales and reset counter
        Sale::withTrashed()->forceDelete();
        Setting::where('group_name', 'sales')
               ->where('key_name', 'code_counter')
               ->update(['value' => '999']);

        $this->actingAs($this->salesmanUser, 'sanctum');

        $sale1Data = ($this->getBaseSaleData)();
        $sale1 = Sale::create(array_merge($sale1Data, [
            'created_by' => $this->salesmanUser->id,
            'updated_by' => $this->salesmanUser->id,
        ]));
        $code1 = (int) $sale1->code;

        $sale2Data = ($this->getBaseSaleData)();
        $sale2 = Sale::create(array_merge($sale2Data, [
            'created_by' => $this->salesmanUser->id,
            'updated_by' => $this->salesmanUser->id,
        ]));
        $code2 = (int) $sale2->code;

        expect($code1)->toBeGreaterThanOrEqual(1000);
        expect($code2)->toBe($code1 + 1);
    });
});

describe('Sale Orders Statistics', function () {
    it('can get sale order statistics', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        for ($i = 0; $i < 3; $i++) {
            ($this->createPendingSaleViaFactory)();
        }
        for ($i = 0; $i < 2; $i++) {
            ($this->createApprovedSaleViaFactory)();
        }

        $trashedSale = ($this->createPendingSaleViaFactory)();
        $trashedSale->delete();

        $response = $this->getJson(route('customers.sale-orders.stats'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'total_sales',
                    'pending_sales',
                    'approved_sales',
                    'trashed_sales',
                    'total_amount',
                    'total_amount_usd',
                    'total_profit',
                    'sales_by_prefix',
                    'sales_by_currency',
                    'recent_approved',
                ]
            ]);

        $stats = $response->json('data');
        expect($stats['total_sales'])->toBe(5);
        expect($stats['pending_sales'])->toBe(3);
        expect($stats['approved_sales'])->toBe(2);
        expect($stats['trashed_sales'])->toBe(1);
    });
});

describe('Sale Orders Soft Delete and Recovery', function () {
    it('can list trashed sale orders', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $sale = ($this->createPendingSaleViaFactory)();
        $sale->delete();

        $response = $this->getJson(route('customers.sale-orders.trashed'));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('salesman can restore their own trashed sale order', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $sale = ($this->createPendingSaleViaFactory)();
        $sale->delete();

        $response = $this->patchJson(route('customers.sale-orders.restore', $sale->id));

        $response->assertOk();
        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'deleted_at' => null
        ]);
    });

    it('admin can force delete sale order', function () {
        $this->actingAs($this->adminUser, 'sanctum');

        $sale = ($this->createPendingSaleViaFactory)();
        $sale->delete();

        $response = $this->deleteJson(route('customers.sale-orders.force-delete', $sale->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
    });

    it('salesman cannot force delete sale orders', function () {
        $this->actingAs($this->salesmanUser, 'sanctum');

        $sale = ($this->createPendingSaleViaFactory)();
        $sale->delete();

        $response = $this->deleteJson(route('customers.sale-orders.force-delete', $sale->id));

        $response->assertForbidden()
            ->assertJson([
                'message' => 'Only admins can permanently delete sales'
            ]);
    });
});
