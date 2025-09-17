<?php

use App\Models\Customers\Sale;
use App\Models\Customers\SaleItems;
use App\Models\Inventory\Inventory;
use App\Models\Inventory\ItemPrice;
use App\Models\Items\Item;
use App\Models\Setups\Warehouse;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\User;
use App\Models\Setting;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;

uses()->group('api', 'customers', 'sales');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');

    // Create sale code counter setting (starting from 1000)
    Setting::create([
        'group_name' => 'sales',
        'key_name' => 'code_counter',
        'value' => '1000',
        'data_type' => 'number',
        'description' => 'Sale code counter starting from 1000'
    ]);

    // Create related models for testing
    $this->warehouse = Warehouse::factory()->create(['name' => 'Main Warehouse']);
    $this->currency = Currency::factory()->eur()->create();

    // Create test items
    $this->item1 = Item::factory()->create([
        'code' => 'ITEM001',
        'short_name' => 'Test Item 1',
        'base_cost' => 50.00,
        'base_sell' => 100.00,
    ]);
    $this->item2 = Item::factory()->create([
        'code' => 'ITEM002',
        'short_name' => 'Test Item 2',
        'base_cost' => 75.00,
        'base_sell' => 150.00,
    ]);

    // Create item prices (cost prices) - use updateOrCreate to handle unique constraint
    ItemPrice::updateOrCreate(
        ['item_id' => $this->item1->id],
        [
            'price_usd' => 45.00, // Cost price
            'effective_date' => now(),
        ]
    );
    ItemPrice::updateOrCreate(
        ['item_id' => $this->item2->id],
        [
            'price_usd' => 70.00, // Cost price
            'effective_date' => now(),
        ]
    );

    // Create initial inventory
    InventoryService::set($this->item1->id, $this->warehouse->id, 100, 'Initial stock');
    InventoryService::set($this->item2->id, $this->warehouse->id, 50, 'Initial stock');

    // Helper method for base sale data
    $this->getBaseSaleData = function ($overrides = []) {
        return array_merge([
            'date' => '2025-01-15',
            'prefix' => 'INV',
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'currency_rate' => 1.25,
            'sub_total' => 200.00,
            'sub_total_usd' => 160.00,
            'total' => 200.00,
            'total_usd' => 160.00,
        ], $overrides);
    };

    // Helper method to create sale via API
    $this->createSaleViaApi = function ($overrides = []) {
        $saleData = ($this->getBaseSaleData)($overrides);

        // Ensure items are provided if not in overrides
        if (!isset($saleData['items'])) {
            $saleData['items'] = [
                [
                    'item_id' => $this->item1->id,
                    'price' => 100.00,
                    'quantity' => 2,
                    'total_price' => 200.00,
                ]
            ];
        }

        $response = $this->postJson(route('customers.sales.store'), $saleData);
        $response->assertCreated();

        return Sale::latest()->first();
    };
});

describe('Sales API', function () {
    it('can list sales', function () {
        Sale::factory()->count(3)->create([
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
        ]);

        $response = $this->getJson(route('customers.sales.index'));

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
                        'warehouse',
                        'currency',
                        'total',
                        'total_usd',
                    ]
                ],
                'pagination'
            ]);

        expect($response->json('data'))->toHaveCount(3);
    });

    it('can create sale with items and reduces inventory correctly', function () {
        // Reset inventory to known values for this test
        InventoryService::set($this->item1->id, $this->warehouse->id, 100, 'Reset for test');
        InventoryService::set($this->item2->id, $this->warehouse->id, 50, 'Reset for test');

        $initialInventory1 = InventoryService::getQuantity($this->item1->id, $this->warehouse->id);
        $initialInventory2 = InventoryService::getQuantity($this->item2->id, $this->warehouse->id);

        $saleData = ($this->getBaseSaleData)([
            'client_po_number' => 'PO-2025-001',
            'note' => 'Test sale with multiple items',
            'sub_total' => 520.00,
            'total' => 520.00,
            'total_usd' => 416.00,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 100.00,
                    'quantity' => 3,
                    'total_price' => 300.00,
                    'note' => 'First item'
                ],
                [
                    'item_id' => $this->item2->id,
                    'price' => 110.00,
                    'quantity' => 2,
                    'total_price' => 220.00,
                    'note' => 'Second item'
                ]
            ]
        ]);

        $response = $this->postJson(route('customers.sales.store'), $saleData);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'code',
                    'items',
                    'warehouse',
                    'currency'
                ]
            ]);

        // Verify sale was created
        $this->assertDatabaseHas('sales', [
            'warehouse_id' => $this->warehouse->id,
            'client_po_number' => 'PO-2025-001',
            'prefix' => 'INV',
        ]);

        // Verify sale items were created
        $sale = Sale::latest()->first();
        expect($sale->saleItems)->toHaveCount(2);

        // Verify inventory was reduced correctly
        $newInventory1 = InventoryService::getQuantity($this->item1->id, $this->warehouse->id);
        $newInventory2 = InventoryService::getQuantity($this->item2->id, $this->warehouse->id);

        expect($newInventory1)->toBe($initialInventory1 - 3);
        expect($newInventory2)->toBe($initialInventory2 - 2);
    });

    it('can update sale with item sync and adjusts inventory', function () {
        // Reset inventory to known values for this test
        InventoryService::set($this->item1->id, $this->warehouse->id, 100, 'Reset for test');
        InventoryService::set($this->item2->id, $this->warehouse->id, 50, 'Reset for test');

        $initialInventory1 = InventoryService::getQuantity($this->item1->id, $this->warehouse->id);
        $initialInventory2 = InventoryService::getQuantity($this->item2->id, $this->warehouse->id);

        // Create initial sale via API
        $sale = ($this->createSaleViaApi)([
            'client_po_number' => 'PO-INITIAL',
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 2,
                    'price' => 100.00,
                    'total_price' => 200.00,
                ]
            ]
        ]);

        $existingItem = $sale->saleItems->first();

        // Verify initial inventory reduction
        $afterCreateInventory1 = InventoryService::getQuantity($this->item1->id, $this->warehouse->id);
        expect($afterCreateInventory1)->toBe($initialInventory1 - 2);

        // Update sale data
        $updateData = [
            'client_po_number' => 'PO-UPDATED',
            'currency_rate' => 1.15,
            'total' => 650.00,
            'total_usd' => 565.22,
            'items' => [
                // Update existing item quantity
                [
                    'id' => $existingItem->id,
                    'item_id' => $this->item1->id,
                    'price' => 105.00,
                    'quantity' => 4, // Increase from 2 to 4
                    'total_price' => 420.00,
                    'note' => 'Updated item'
                ],
                // Add new item
                [
                    'item_id' => $this->item2->id,
                    'price' => 115.00,
                    'quantity' => 2,
                    'total_price' => 230.00,
                    'note' => 'New item added'
                ]
            ]
        ];

        $response = $this->putJson(route('customers.sales.update', $sale), $updateData);
        $response->assertOk();

        // Verify sale was updated
        $sale->refresh();
        expect($sale->client_po_number)->toBe('PO-UPDATED');
        expect($sale->currency_rate)->toBe('1.1500');

        // Verify items were synced
        expect($sale->saleItems)->toHaveCount(2);

        // Verify inventory adjustments
        $finalInventory1 = InventoryService::getQuantity($this->item1->id, $this->warehouse->id);
        $finalInventory2 = InventoryService::getQuantity($this->item2->id, $this->warehouse->id);

        // Item1: was 2, now 4 = additional 2 reduction
        expect($finalInventory1)->toBe($initialInventory1 - 4);
        // Item2: was 0, now 2 = reduction of 2
        expect($finalInventory2)->toBe($initialInventory2 - 2);
    });

    it('can show sale with all relationships', function () {
        $sale = ($this->createSaleViaApi)();

        $response = $this->getJson(route('customers.sales.show', $sale));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'code',
                    'sale_code',
                    'warehouse' => ['id', 'name'],
                    'currency' => ['id', 'name', 'code', 'symbol'],
                    'items' => [
                        '*' => [
                            'id',
                            'item_code',
                            'price',
                            'quantity',
                            'total_price'
                        ]
                    ]
                ]
            ]);
    });

    it('auto-generates sale codes when not provided', function () {
        $saleData = ($this->getBaseSaleData)([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 100.00,
                    'quantity' => 1,
                    'total_price' => 100.00,
                ]
            ]
        ]);

        $response = $this->postJson(route('customers.sales.store'), $saleData);

        $response->assertCreated();

        $sale = Sale::where('warehouse_id', $this->warehouse->id)->first();
        expect($sale->code)->not()->toBeNull();
        expect($sale->code)->toMatch('/^\d{6}$/'); // 6-digit padded number
    });

    it('can soft delete sale', function () {
        $sale = Sale::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response = $this->deleteJson(route('customers.sales.destroy', $sale));

        $response->assertStatus(204);
        $this->assertSoftDeleted('sales', ['id' => $sale->id]);
    });

    it('can list trashed sales', function () {
        $sale = Sale::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);
        $sale->delete();

        $response = $this->getJson(route('customers.sales.trashed'));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can restore trashed sale', function () {
        $sale = Sale::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);
        $sale->delete();

        $response = $this->patchJson(route('customers.sales.restore', $sale->id));

        $response->assertOk();
        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'deleted_at' => null
        ]);
    });

    it('can force delete sale', function () {
        $sale = Sale::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);
        $sale->delete();

        $response = $this->deleteJson(route('customers.sales.force-delete', $sale->id));

        $response->assertStatus(204);
        $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
    });

    it('validates required fields when creating', function () {
        $invalidData = [
            'warehouse_id' => null,
            'currency_id' => null,
            'total' => -100, // Negative total
            'items' => [
                [
                    'item_id' => 999, // Non-existent item
                    'price' => -10, // Negative price
                    'quantity' => 0, // Zero quantity
                ]
            ]
        ];

        $response = $this->postJson(route('customers.sales.store'), $invalidData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'warehouse_id',
                'currency_id',
                'items.0.item_id',
                'items.0.price',
                'items.0.quantity'
            ]);
    });

    it('rollbacks transaction when inventory is insufficient', function () {
        // Set low inventory for item1
        InventoryService::set($this->item1->id, $this->warehouse->id, 1, 'Low stock for test');

        $saleData = ($this->getBaseSaleData)([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 100.00,
                    'quantity' => 5, // More than available (1)
                    'total_price' => 500.00,
                ]
            ]
        ]);

        $initialSalesCount = Sale::count();
        $initialSaleItemsCount = SaleItems::count();

        // This should fail due to insufficient inventory
        $response = $this->postJson(route('customers.sales.store'), $saleData);

        $response->assertStatus(500);

        // Verify no sale or sale items were created
        expect(Sale::count())->toBe($initialSalesCount);
        expect(SaleItems::count())->toBe($initialSaleItemsCount);

        // Verify inventory was not changed
        $inventory = InventoryService::getQuantity($this->item1->id, $this->warehouse->id);
        expect($inventory)->toBe(1); // Should remain unchanged
    });

    it('rollbacks transaction when validation fails mid-creation', function () {
        $initialSalesCount = Sale::count();
        $initialSaleItemsCount = SaleItems::count();
        $initialInventory = InventoryService::getQuantity($this->item1->id, $this->warehouse->id);

        // Create sale data with mixed valid/invalid items
        $saleData = ($this->getBaseSaleData)([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 100.00,
                    'quantity' => 2,
                    'total_price' => 200.00,
                ],
                [
                    'item_id' => 999, // Invalid item ID - should cause failure
                    'price' => 150.00,
                    'quantity' => 1,
                    'total_price' => 150.00,
                ]
            ]
        ]);

        $response = $this->postJson(route('customers.sales.store'), $saleData);

        $response->assertUnprocessable();

        // Verify nothing was created and inventory unchanged
        expect(Sale::count())->toBe($initialSalesCount);
        expect(SaleItems::count())->toBe($initialSaleItemsCount);

        $finalInventory = InventoryService::getQuantity($this->item1->id, $this->warehouse->id);
        expect($finalInventory)->toBe($initialInventory);
    });

    it('restores inventory when sale item is deleted', function () {
        $initialInventory = InventoryService::getQuantity($this->item1->id, $this->warehouse->id);

        // Create sale with item
        $sale = ($this->createSaleViaApi)([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 5,
                    'price' => 100.00,
                    'total_price' => 500.00,
                ]
            ]
        ]);

        // Verify inventory was reduced
        $afterSaleInventory = InventoryService::getQuantity($this->item1->id, $this->warehouse->id);
        expect($afterSaleInventory)->toBe($initialInventory - 5);

        // Delete sale (soft delete)
        $sale->delete();

        // Note: Inventory restoration happens in the deleted event
        // Verify inventory was restored
        $finalInventory = InventoryService::getQuantity($this->item1->id, $this->warehouse->id);
        expect($finalInventory)->toBe($initialInventory); // Should be back to original
    });

    it('adjusts inventory correctly when sale item quantity is changed', function () {
        $initialInventory = InventoryService::getQuantity($this->item1->id, $this->warehouse->id);

        // Create sale
        $sale = ($this->createSaleViaApi)([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 3,
                    'price' => 100.00,
                    'total_price' => 300.00,
                ]
            ]
        ]);

        $saleItem = $sale->saleItems->first();

        // Verify initial inventory reduction
        $afterCreateInventory = InventoryService::getQuantity($this->item1->id, $this->warehouse->id);
        expect($afterCreateInventory)->toBe($initialInventory - 3);

        // Update sale item quantity to 5 (increase by 2)
        $updateData = [
            'items' => [
                [
                    'id' => $saleItem->id,
                    'item_id' => $this->item1->id,
                    'quantity' => 5, // Increase from 3 to 5
                    'price' => 100.00,
                    'total_price' => 500.00,
                ]
            ]
        ];

        $response = $this->putJson(route('customers.sales.update', $sale), $updateData);
        $response->assertOk();

        // Verify inventory was further reduced by 2
        $finalInventory = InventoryService::getQuantity($this->item1->id, $this->warehouse->id);
        expect($finalInventory)->toBe($initialInventory - 5);

        // Update sale item quantity to 1 (decrease by 4)
        $updateData['items'][0]['quantity'] = 1;
        $updateData['items'][0]['total_price'] = 100.00;

        $response = $this->putJson(route('customers.sales.update', $sale), $updateData);
        $response->assertOk();

        // Verify inventory was restored by 4
        $finalInventory2 = InventoryService::getQuantity($this->item1->id, $this->warehouse->id);
        expect($finalInventory2)->toBe($initialInventory - 1);
    });

    it('handles concurrent sale creation properly with transactions', function () {
        // Set limited inventory
        InventoryService::set($this->item1->id, $this->warehouse->id, 5, 'Limited stock for concurrent test');

        $saleData = ($this->getBaseSaleData)([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 100.00,
                    'quantity' => 3,
                    'total_price' => 300.00,
                ]
            ]
        ]);

        // Create first sale
        $response1 = $this->postJson(route('customers.sales.store'), $saleData);
        $response1->assertCreated();

        // Verify inventory after first sale
        $inventory = InventoryService::getQuantity($this->item1->id, $this->warehouse->id);
        expect($inventory)->toBe(2); // 5 - 3 = 2

        // Create second sale
        $response2 = $this->postJson(route('customers.sales.store'), $saleData);

        // This should fail due to insufficient inventory (need 3, only have 2)
        $response2->assertStatus(500);

        // Verify inventory remained at 2
        $finalInventory = InventoryService::getQuantity($this->item1->id, $this->warehouse->id);
        expect($finalInventory)->toBe(2);

        // Verify only one sale was created
        expect(Sale::count())->toBe(1);
    });

    it('can filter sales by warehouse', function () {
        $otherWarehouse = Warehouse::factory()->create(['name' => 'Other Warehouse']);

        Sale::factory()->create(['warehouse_id' => $this->warehouse->id]);
        Sale::factory()->create(['warehouse_id' => $otherWarehouse->id]);

        $response = $this->getJson(route('customers.sales.index', ['warehouse_id' => $this->warehouse->id]));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can filter sales by currency', function () {
        $otherCurrency = Currency::factory()->usd()->create();

        Sale::factory()->create(['currency_id' => $this->currency->id]);
        Sale::factory()->create(['currency_id' => $otherCurrency->id]);

        $response = $this->getJson(route('customers.sales.index', ['currency_id' => $this->currency->id]));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can filter sales by date range', function () {
        Sale::factory()->create(['date' => '2025-01-01']);
        Sale::factory()->create(['date' => '2025-02-15']);
        Sale::factory()->create(['date' => '2025-03-30']);

        $response = $this->getJson(route('customers.sales.index', [
            'start_date' => '2025-02-01',
            'end_date' => '2025-02-28'
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can search sales by code', function () {
        $sale1 = Sale::factory()->create([
            'code' => '001001',
            'warehouse_id' => $this->warehouse->id,
        ]);
        Sale::factory()->create([
            'code' => '001002',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response = $this->getJson(route('customers.sales.index', ['search' => '001001']));

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->toHaveCount(1);
        expect($data[0]['code'])->toBe('001001');
    });

    it('can search sales by client PO number', function () {
        Sale::factory()->create([
            'client_po_number' => 'PO-12345',
            'warehouse_id' => $this->warehouse->id,
        ]);
        Sale::factory()->create([
            'client_po_number' => 'PO-67890',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response = $this->getJson(route('customers.sales.index', ['search' => 'PO-12345']));

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->toHaveCount(1);
        expect($data[0]['client_po_number'])->toBe('PO-12345');
    });

    it('sets created_by and updated_by fields automatically', function () {
        $sale = Sale::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);

        expect($sale->created_by)->toBe($this->user->id);
        expect($sale->updated_by)->toBe($this->user->id);

        $sale->update(['note' => 'Updated note']);
        expect($sale->fresh()->updated_by)->toBe($this->user->id);
    });

    it('returns 404 for non-existent sale', function () {
        $response = $this->getJson(route('customers.sales.show', 999));

        $response->assertNotFound();
    });

    it('can paginate sales', function () {
        Sale::factory()->count(7)->create([
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response = $this->getJson(route('customers.sales.index', ['per_page' => 3]));

        $response->assertOk();
        $data = $response->json('data');
        $pagination = $response->json('pagination');

        expect($data)->toHaveCount(3);
        expect($pagination['total'])->toBe(7);
        expect($pagination['per_page'])->toBe(3);
        expect($pagination['last_page'])->toBe(3);
    });

    it('can get sale statistics', function () {
        Sale::factory()->count(5)->create([
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'prefix' => 'INV',
            'total' => 1000.00,
            'total_usd' => 800.00,
        ]);

        $response = $this->getJson(route('customers.sales.stats'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'total_sales',
                    'trashed_sales',
                    'total_amount',
                    'total_amount_usd',
                    'sales_by_prefix',
                    'sales_by_warehouse',
                    'sales_by_currency',
                ]
            ]);

        $stats = $response->json('data');
        expect($stats['total_sales'])->toBe(5);
    });

    it('automatically calculates cost price from item price', function () {
        $saleData = ($this->getBaseSaleData)([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 100.00, // Selling price
                    'quantity' => 2,
                    'total_price' => 200.00,
                ]
            ]
        ]);

        $response = $this->postJson(route('customers.sales.store'), $saleData);
        $response->assertCreated();

        $sale = Sale::latest()->first();
        $saleItem = $sale->saleItems->first();

        // Verify cost price was automatically set from item's price
        expect((float)$saleItem->cost_price)->toBe(45.00); // From ItemPrice
    });

    it('calculates unit profit correctly', function () {
        $saleData = ($this->getBaseSaleData)([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 100.00, // Selling price
                    'quantity' => 1,
                    'total_price' => 100.00,
                ]
            ]
        ]);

        $response = $this->postJson(route('customers.sales.store'), $saleData);
        $response->assertCreated();

        $sale = Sale::latest()->first();
        $saleItem = $sale->saleItems->first();

        // Unit profit = selling price - cost price = 100.00 - 45.00 = 55.00
        expect((float)$saleItem->unit_profit)->toBe(55.00);
    });

    it('calculates total profit per item correctly', function () {
        $saleData = ($this->getBaseSaleData)([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 100.00, // Selling price
                    'quantity' => 3,
                    'total_price' => 300.00,
                ]
            ]
        ]);

        $response = $this->postJson(route('customers.sales.store'), $saleData);
        $response->assertCreated();

        $sale = Sale::latest()->first();
        $saleItem = $sale->saleItems->first();

        // Total profit = unit profit × quantity = 55.00 × 3 = 165.00
        expect((float)$saleItem->total_profit)->toBe(165.00);
    });

    it('calculates total sale profit correctly', function () {
        $saleData = ($this->getBaseSaleData)([
            'total' => 520.00,
            'total_usd' => 416.00,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 100.00, // Selling price, cost: 45.00, unit profit: 55.00
                    'quantity' => 2,
                    'total_price' => 200.00, // Total profit: 110.00
                ],
                [
                    'item_id' => $this->item2->id,
                    'price' => 160.00, // Selling price, cost: 70.00, unit profit: 90.00
                    'quantity' => 2,
                    'total_price' => 320.00, // Total profit: 180.00
                ]
            ]
        ]);

        $response = $this->postJson(route('customers.sales.store'), $saleData);
        $response->assertCreated();

        $sale = Sale::latest()->first();

        // Total sale profit = 110.00 + 180.00 = 290.00
        expect((float)$sale->total_profit)->toBe(290.00);
    });

    it('includes profit fields in API response', function () {
        $saleData = ($this->getBaseSaleData)([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 100.00,
                    'quantity' => 2,
                    'total_price' => 200.00,
                ]
            ]
        ]);

        $response = $this->postJson(route('customers.sales.store'), $saleData);
        $response->assertCreated();

        $sale = Sale::latest()->first();
        $response = $this->getJson(route('customers.sales.show', $sale));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_profit',
                    'items' => [
                        '*' => [
                            'cost_price',
                            'unit_profit',
                            'total_profit'
                        ]
                    ]
                ]
            ]);

        $data = $response->json('data');
        expect((float)$data['total_profit'])->toBe(110.00);
        expect((float)$data['items'][0]['cost_price'])->toBe(45.00);
        expect((float)$data['items'][0]['unit_profit'])->toBe(55.00);
        expect((float)$data['items'][0]['total_profit'])->toBe(110.00);
    });

    it('updates profits when sale items are updated', function () {
        // Reset inventory to known values for this test
        InventoryService::set($this->item1->id, $this->warehouse->id, 100, 'Reset for test');
        InventoryService::set($this->item2->id, $this->warehouse->id, 50, 'Reset for test');

        // Create initial sale
        $sale = ($this->createSaleViaApi)([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 100.00,
                    'quantity' => 1,
                    'total_price' => 100.00,
                ]
            ]
        ]);

        $saleItem = $sale->saleItems->first();

        // Update sale with different quantities and items
        $updateData = [
            'total' => 520.00,
            'total_usd' => 416.00,
            'items' => [
                [
                    'id' => $saleItem->id,
                    'item_id' => $this->item1->id,
                    'price' => 105.00, // New selling price
                    'quantity' => 2, // Increased quantity
                    'total_price' => 210.00,
                ],
                [
                    'item_id' => $this->item2->id,
                    'price' => 155.00,
                    'quantity' => 2,
                    'total_price' => 310.00,
                ]
            ]
        ];

        $response = $this->putJson(route('customers.sales.update', $sale), $updateData);
        $response->assertOk();

        $sale->refresh();
        $saleItems = $sale->saleItems;

        // Verify we have 2 sale items
        expect($saleItems)->toHaveCount(2);

        // Check first item profits (selling: 105, cost: 45, unit profit: 60)
        $firstItem = $saleItems->where('item_id', $this->item1->id)->first();
        expect($firstItem)->not()->toBeNull();
        expect((float)$firstItem->cost_price)->toBe(45.00);
        expect((float)$firstItem->unit_profit)->toBe(60.00);
        expect((float)$firstItem->total_profit)->toBe(120.00); // 60 × 2

        // Check second item profits (selling: 155, cost: 70, unit profit: 85)
        $secondItem = $saleItems->where('item_id', $this->item2->id)->first();
        expect($secondItem)->not()->toBeNull();
        expect((float)$secondItem->cost_price)->toBe(70.00);
        expect((float)$secondItem->unit_profit)->toBe(85.00);
        expect((float)$secondItem->total_profit)->toBe(170.00); // 85 × 2

        // Check total sale profit: 120 + 170 = 290
        expect((float)$sale->total_profit)->toBe(290.00);
    });

    it('handles zero cost price correctly', function () {
        // Create an item without price (cost price will be 0)
        $itemWithoutPrice = Item::factory()->create(['code' => 'ITEM003']);

        // Ensure no ItemPrice record exists for this item
        \App\Models\Inventory\ItemPrice::where('item_id', $itemWithoutPrice->id)->delete();

        // Create inventory for this item
        InventoryService::set($itemWithoutPrice->id, $this->warehouse->id, 10, 'Test inventory');

        $saleData = ($this->getBaseSaleData)([
            'items' => [
                [
                    'item_id' => $itemWithoutPrice->id,
                    'price' => 100.00,
                    'quantity' => 1,
                    'total_price' => 100.00,
                ]
            ]
        ]);

        $response = $this->postJson(route('customers.sales.store'), $saleData);
        $response->assertCreated();

        $sale = Sale::latest()->first();
        $saleItem = $sale->saleItems->first();

        expect((float)$saleItem->cost_price)->toBe(0.00);
        expect((float)$saleItem->unit_profit)->toBe(100.00); // 100 - 0
        expect((float)$saleItem->total_profit)->toBe(100.00); // 100 × 1
        expect((float)$sale->total_profit)->toBe(100.00);
    });
});

describe('Sale Code Generation Tests', function () {
    // it('gets next code when current setting is 1001', function () {
    //     Setting::set('sales', 'code_counter', 1001, 'number');

    //     $nextCode = Sale::getNextSuggestedCode ?? '001001';

    //     expect($nextCode)->toContain('001001');
    // });

    it('creates sale with current counter and increments correctly', function () {
        Setting::set('sales', 'code_counter', 1005, 'number');

        $response = $this->postJson(route('customers.sales.store'), ($this->getBaseSaleData)([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 100.00,
                    'quantity' => 1,
                    'total_price' => 100.00,
                ]
            ]
        ]));

        $response->assertCreated();
        $code = $response->json('data.code');
        expect($code)->toBe('001005');
    });

    it('generates sequential sale codes', function () {
        Sale::withTrashed()->forceDelete();

        $response1 = $this->postJson(route('customers.sales.store'), ($this->getBaseSaleData)([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 100.00,
                    'quantity' => 1,
                    'total_price' => 100.00,
                ]
            ]
        ]));
        $response1->assertCreated();
        $code1 = $response1->json('data.code');

        $response2 = $this->postJson(route('customers.sales.store'), ($this->getBaseSaleData)([
            'client_po_number' => 'PO-2025-002',
            'items' => [
                [
                    'item_id' => $this->item2->id,
                    'price' => 150.00,
                    'quantity' => 1,
                    'total_price' => 150.00,
                ]
            ]
        ]));
        $response2->assertCreated();
        $code2 = $response2->json('data.code');

        $num1 = (int) $code1;
        $num2 = (int) $code2;
        expect($num2)->toBe($num1 + 1);
    });
});
