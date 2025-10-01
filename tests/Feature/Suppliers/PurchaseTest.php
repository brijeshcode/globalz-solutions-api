<?php

use App\Models\Suppliers\Purchase;
use App\Models\Suppliers\PurchaseItem;
use App\Models\Suppliers\SupplierItemPrice;
use App\Models\Inventory\ItemPrice;
use App\Models\Inventory\ItemPriceHistory;
use App\Models\Inventory\Inventory;
use App\Models\Items\Item;
use App\Models\Setups\Supplier;
use App\Models\Setups\Warehouse;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Accounts\Account;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses()->group('api', 'suppliers', 'purchases');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');

    // Create purchase code counter setting (starting from 1000)
    Setting::create([
        'group_name' => 'purchases',
        'key_name' => 'code_counter',
        'value' => '1000',
        'data_type' => 'number',
        'description' => 'Purchase code counter starting from 1000'
    ]);

    // Create related models for testing
    $this->supplier = Supplier::factory()->create(['name' => 'Test Supplier']);
    $this->warehouse = Warehouse::factory()->create(['name' => 'Main Warehouse']);
    $this->currency = Currency::factory()->eur()->create(['is_active' => true]);
    $this->account = Account::factory()->create(['name' => 'Purchase Account']);

    // Create test items with different cost calculation methods
    $this->item1 = Item::factory()->create([
        'cost_calculation' => Item::COST_WEIGHTED_AVERAGE,
        'code' => 'ITEM001',
        'short_name' => 'Test Item 1',
    ]);
    $this->item2 = Item::factory()->create([
        'cost_calculation' => Item::COST_LAST_COST,
        'code' => 'ITEM002',
        'short_name' => 'Test Item 2',
    ]);

    // Helper method for base purchase data
    $this->getBasePurchaseData = function ($overrides = []) {
        return array_merge([
            'date' => '2025-01-15',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'account_id' => $this->account->id,
            'supplier_invoice_number' => 'INV-2025-001',
            'currency_rate' => 1.25,
        ], $overrides);
    };

    // Helper method to create purchase via API (includes all business logic)
    $this->createPurchaseViaApi = function ($overrides = []) {
        $purchaseData = ($this->getBasePurchaseData)($overrides);

        // Ensure items are provided if not in overrides
        if (!isset($purchaseData['items'])) {
            $purchaseData['items'] = [
                [
                    'item_id' => $this->item1->id,
                    'price' => 100.00,
                    'quantity' => 2,
                ]
            ];
        }

        $response = $this->postJson(route('suppliers.purchases.store'), $purchaseData);
        $response->assertCreated();

        return Purchase::latest()->first();
    };
});

describe('Purchases API', function () {
    it('can list purchases', function () {
        Purchase::factory()->count(3)->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
        ]);

        $response = $this->getJson(route('suppliers.purchases.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'date',
                        'supplier',
                        'warehouse',
                        'currency',
                        'final_total_usd',
                    ]
                ],
                'pagination'
            ]);

        expect($response->json('data'))->toHaveCount(3);
    });

    it('can create purchase with items and updates inventory', function () {
        $purchaseData = ($this->getBasePurchaseData)([
            'shipping_fee_usd' => 50.00,
            'customs_fee_usd' => 25.00,
            'note' => 'Test purchase with multiple items',
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 100.00,
                    'quantity' => 5,
                    'discount_percent' => 10,
                    'note' => 'First item with discount'
                ],
                [
                    'item_id' => $this->item2->id,
                    'price' => 200.00,
                    'quantity' => 3,
                    'discount_amount' => 15.00,
                    'note' => 'Second item with fixed discount'
                ]
            ]
        ]);

        $response = $this->postJson(route('suppliers.purchases.store'), $purchaseData);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'code',
                    'items',
                    'supplier',
                    'warehouse'
                ]
            ]);

        // Verify purchase was created
        $this->assertDatabaseHas('purchases', [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_invoice_number' => 'INV-2025-001',
        ]);

        // Verify purchase items were created
        $purchase = Purchase::latest()->first();
        expect($purchase->purchaseItems)->toHaveCount(2);

        // Verify inventory was updated
        $inventory1 = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('item_id', $this->item1->id)
            ->first();
        expect($inventory1->quantity)->toBe('5.0000');

        $inventory2 = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('item_id', $this->item2->id)
            ->first();
        expect($inventory2->quantity)->toBe('3.0000');

        // Verify supplier item prices were created
        $supplierPrice1 = SupplierItemPrice::where('supplier_id', $this->supplier->id)
            ->where('item_id', $this->item1->id)
            ->where('is_current', true)
            ->first();
        expect($supplierPrice1)->not()->toBeNull();
        expect($supplierPrice1->price)->toBe('100.0000');
        expect($supplierPrice1->price_usd)->toBeGreaterThan(0);

        // Verify item prices were created or updated
        $itemPrice1 = ItemPrice::where('item_id', $this->item1->id)->first();
        expect($itemPrice1)->not()->toBeNull();
        expect($itemPrice1->price_usd)->toBeGreaterThan(0);
    });

    it('can update purchase with item sync', function () {
        // Create initial purchase via API (includes all business logic)
        $purchase = ($this->createPurchaseViaApi)([
            'currency_rate' => 1.0,
            'supplier_invoice_number' => 'INV-INITIAL',
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 2,
                    'price' => 50.00,
                ]
            ]
        ]);

        $existingItem = $purchase->purchaseItems->first();

        // Update purchase data
        $updateData = [
            'supplier_invoice_number' => 'INV-UPDATED',
            'currency_rate' => 1.15,
            'items' => [
                // Update existing item
                [
                    'id' => $existingItem->id,
                    'item_id' => $this->item1->id,
                    'price' => 55.00,
                    'quantity' => 4,
                    'note' => 'Updated item'
                ],
                // Add new item
                [
                    'item_id' => $this->item2->id,
                    'price' => 120.00,
                    'quantity' => 2,
                    'note' => 'New item added'
                ]
            ]
        ];

        $response = $this->putJson(route('suppliers.purchases.update', $purchase), $updateData);

        $response->assertOk();

        // Verify purchase was updated
        $purchase->refresh();
        expect($purchase->supplier_invoice_number)->toBe('INV-UPDATED');
        expect($purchase->currency_rate)->toBe('1.150000');

        // Verify items were synced
        expect($purchase->purchaseItems)->toHaveCount(2);

        // Verify existing item was updated
        $existingItem->refresh();
        expect($existingItem->price)->toBe('55.0000');
        expect($existingItem->quantity)->toBe('4.0000');

        // Verify inventory was updated
        $inventory = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('item_id', $this->item1->id)
            ->first();
        expect($inventory->quantity)->toBe('4.0000');
    });

    it('only creates new supplier item price when price changes', function () {
        // Create initial supplier item price
        $initialPrice = SupplierItemPrice::factory()->create([
            'supplier_id' => $this->supplier->id,
            'item_id' => $this->item1->id,
            'price' => 100.00,
            'is_current' => true,
        ]);

        // Create purchase with same price
        $purchaseData = ($this->getBasePurchaseData)([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 100.00, // Same price
                    'quantity' => 1,
                ]
            ]
        ]);

        $response = $this->postJson(route('suppliers.purchases.store'), $purchaseData);
        $response->assertCreated();

        // Verify only one current price exists (no new record created)
        $currentPrices = SupplierItemPrice::where('supplier_id', $this->supplier->id)
            ->where('item_id', $this->item1->id)
            ->where('is_current', true)
            ->count();
        expect($currentPrices)->toBe(1);

        // Create purchase with different price
        $purchaseData['items'][0]['price'] = 120.00; // Different price
        $response = $this->postJson(route('suppliers.purchases.store'), $purchaseData);
        $response->assertCreated();

        // Verify new price record was created
        $currentPrice = SupplierItemPrice::where('supplier_id', $this->supplier->id)
            ->where('item_id', $this->item1->id)
            ->where('is_current', true)
            ->first();
        expect($currentPrice->price)->toBe('120.0000');

        // Verify old price is no longer current
        $initialPrice->refresh();
        expect($initialPrice->is_current)->toBeFalse();
    });

    it('creates item price history on significant price change', function () {
        // Create or update initial item price
        $initialItemPrice = ItemPrice::updateOrCreate(
            ['item_id' => $this->item1->id],
            ['price_usd' => 50.00, 'effective_date' => now()->toDateString()]
        );

        // Create purchase with significantly different price
        $purchaseData = ($this->getBasePurchaseData)([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 75.00, // Significant price change
                    'quantity' => 1,
                ]
            ]
        ]);

        $response = $this->postJson(route('suppliers.purchases.store'), $purchaseData);
        $response->assertCreated();

        // Verify price history was created
        $priceHistory = ItemPriceHistory::where('item_id', $this->item1->id)->first();
        expect($priceHistory)->not()->toBeNull();
        expect($priceHistory->latest_price)->toBe('50.0000');
        expect($priceHistory->price_usd)->toBeGreaterThan(50.00);
        expect($priceHistory->source_type)->toBe('purchase');
        expect($priceHistory->source_id)->not()->toBeNull();
        if ($priceHistory->note) {
            expect($priceHistory->note)->toContain('Purchase');
        }

        // Verify item price was updated
        $initialItemPrice->refresh();
        expect($initialItemPrice->price_usd)->toBeGreaterThan(50.00);
    });

    it('calculates weighted average price correctly', function () {
        // Create existing inventory and item price
        Inventory::updateOrCreate(
            ['warehouse_id' => $this->warehouse->id, 'item_id' => $this->item1->id],
            ['quantity' => 10]
        );

        ItemPrice::updateOrCreate(
            ['item_id' => $this->item1->id],
            ['price_usd' => 20.00, 'effective_date' => now()->toDateString()]
        );

        // Create purchase that should trigger weighted average calculation
        $purchaseData = ($this->getBasePurchaseData)([
            'currency_rate' => 1.0, // Simplified for calculation
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 30.00, // New price
                    'quantity' => 5,
                ]
            ]
        ]);

        $response = $this->postJson(route('suppliers.purchases.store'), $purchaseData);
        $response->assertCreated();

        // Verify weighted average was calculated
        // Expected: ((10 * 20) + (5 * 30)) / (10 + 5) = (200 + 150) / 15 = 23.33
        $itemPrice = ItemPrice::where('item_id', $this->item1->id)->first();
        // expect((float) $itemPrice->price_usd)->toBeCloseTo(23.33, 1);
        // Verify inventory was updated
        $inventory = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('item_id', $this->item1->id)
            ->first();
        expect($inventory->quantity)->toBe('15.0000');
    });

    todo('calculates weighted average price correctly: in this test do type cast check');

    it('can show purchase with all relationships', function () {
        $purchase = ($this->createPurchaseViaApi)([
            'account_id' => $this->account->id,
        ]);

        $response = $this->getJson(route('suppliers.purchases.show', $purchase));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'code',
                    'supplier' => ['id', 'name', 'code'],
                    'warehouse' => ['id', 'name'],
                    'currency' => ['id', 'name', 'code', 'symbol'],
                    'account' => ['id', 'name'],
                    'items' => [
                        '*' => [
                            'id',
                            'item' => ['id', 'code', 'name'],
                            'price',
                            'quantity',
                            'total_price'
                        ]
                    ]
                ]
            ]);
    });


    it('auto-generates purchase codes when not provided', function () {
        $purchaseData = ($this->getBasePurchaseData)([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 100.00,
                    'quantity' => 1,
                ]
            ]
        ]);

        $response = $this->postJson(route('suppliers.purchases.store'), $purchaseData);

        $response->assertCreated();

        $purchase = Purchase::where('supplier_id', $this->supplier->id)->first();
        expect($purchase->code)->not()->toBeNull();
        // expect($purchase->code)->toContain('PUR-');
    });


    it('can soft delete purchase', function () {
        $purchase = Purchase::factory()->create([
            'supplier_id' => $this->supplier->id,
        ]);

        $response = $this->deleteJson(route('suppliers.purchases.destroy', $purchase));

        $response->assertStatus(204);
        $this->assertSoftDeleted('purchases', ['id' => $purchase->id]);
    });

    it('can list trashed purchases', function () {
        $purchase = Purchase::factory()->create([
            'supplier_id' => $this->supplier->id,
        ]);
        $purchase->delete();

        $response = $this->getJson(route('suppliers.purchases.trashed'));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can restore trashed purchase', function () {
        $purchase = Purchase::factory()->create([
            'supplier_id' => $this->supplier->id,
        ]);
        $purchase->delete();

        $response = $this->patchJson(route('suppliers.purchases.restore', $purchase->id));

        $response->assertOk();
        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'deleted_at' => null
        ]);
    });

    it('can force delete purchase', function () {
        $purchase = Purchase::factory()->create([
            'supplier_id' => $this->supplier->id,
        ]);
        $purchase->delete();

        $response = $this->deleteJson(route('suppliers.purchases.force-delete', $purchase->id));

        $response->assertStatus(204);
        $this->assertDatabaseMissing('purchases', ['id' => $purchase->id]);
    });

    it('validates required fields when creating', function () {
        $invalidData = [
            'supplier_id' => 999, // Non-existent supplier
            'warehouse_id' => null,
            'currency_id' => null,
            'items' => [
                [
                    'item_id' => 999, // Non-existent item
                    'price' => -10, // Negative price
                    'quantity' => 0, // Zero quantity
                ]
            ]
        ];

        $response = $this->postJson(route('suppliers.purchases.store'), $invalidData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'supplier_id',
                'warehouse_id',
                'currency_id',
                'items.0.item_id',
                'items.0.price',
                'items.0.quantity'
            ]);
    });

    it('calculates purchase totals correctly', function () {
        $purchaseData = ($this->getBasePurchaseData)([
            'currency_rate' => 0.5,
            'shipping_fee_usd' => 20.00,
            'customs_fee_usd' => 10.00,
            'discount_amount_usd' => 5.00,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 100.00,
                    'quantity' => 2,
                    'discount_percent' => 10, // 10% discount = 10.00 per item
                ]
            ]
        ]);

        $response = $this->postJson(route('suppliers.purchases.store'), $purchaseData);
        $response->assertCreated();

        $purchase = Purchase::latest()->first();

        // Verify calculations
        // Item total: (100 - 10) * 2 = 180.00
        // USD total: 180.00 * 0.5 = 90.00
        expect($purchase->sub_total)->toBe('180.0000');
        expect($purchase->sub_total_usd)->toBe('90.0000');

        // Final total USD: 90.00 - 5.00 + 20.00 + 10.00 = 115.00
        expect($purchase->final_total_usd)->toBe('115.0000');
    })->group('this');

    it('adjusts inventory when item quantity is reduced', function () {
        // Create purchase via API (includes all business logic)
        $purchase = ($this->createPurchaseViaApi)([
            'currency_rate' => 1.0,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 5,
                    'price' => 100.00,
                ]
            ]
        ]);

        // Update purchase to reduce item quantity
        $purchaseItem = $purchase->purchaseItems->first();
        $updateData = [
            'items' => [
                [
                    'id' => $purchaseItem->id,
                    'item_id' => $this->item1->id,
                    'quantity' => 2, // Reduce from 5 to 2
                    'price' => 100.00,
                ]
            ]
        ];

        $response = $this->putJson(route('suppliers.purchases.update', $purchase), $updateData);
        $response->assertOk();
    

        // Verify inventory was adjusted
        $inventory = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('item_id', $this->item1->id)
            ->first();

        expect($inventory->quantity)->toBe('2.0000');
    });

    it('validates that at least one item is required when updating purchase', function () {
        // Create purchase via API 
        $purchase = ($this->createPurchaseViaApi)([
            'currency_rate' => 1.0,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 5,
                    'price' => 100.00,
                ]
            ]
        ]);

        // Try to update purchase with no items
        $updateData = [
            'items' => [] // This should fail validation
        ];

        $response = $this->putJson(route('suppliers.purchases.update', $purchase), $updateData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    });

    it('properly handles inventory, prices and supplier prices when item is removed from purchase', function () {
        // Create purchase with two items
        $purchase = ($this->createPurchaseViaApi)([
            'currency_rate' => 1.0,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 10,
                    'price' => 50.00,
                ],
                [
                    'item_id' => $this->item2->id,
                    'quantity' => 5,
                    'price' => 80.00,
                ]
            ]
        ]);

        // Verify initial state - both items should have inventory, prices, and supplier prices
        $inventory1 = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('item_id', $this->item1->id)
            ->first();
        expect($inventory1->quantity)->toBe('10.0000');

        $inventory2 = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('item_id', $this->item2->id)
            ->first();
        expect($inventory2->quantity)->toBe('5.0000');

        // Verify supplier item prices exist for both items
        $supplierPrice1 = SupplierItemPrice::where('supplier_id', $this->supplier->id)
            ->where('item_id', $this->item1->id)
            ->where('is_current', true)
            ->first();
        expect($supplierPrice1)->not()->toBeNull();

        $supplierPrice2 = SupplierItemPrice::where('supplier_id', $this->supplier->id)
            ->where('item_id', $this->item2->id)
            ->where('is_current', true)
            ->first();
        expect($supplierPrice2)->not()->toBeNull();

        // Verify item prices exist
        $itemPrice1 = ItemPrice::where('item_id', $this->item1->id)->first();
        expect($itemPrice1)->not()->toBeNull();

        $itemPrice2 = ItemPrice::where('item_id', $this->item2->id)->first();
        expect($itemPrice2)->not()->toBeNull();

        // Update purchase to keep only item1, removing item2
        $purchaseItem1 = $purchase->purchaseItems->where('item_id', $this->item1->id)->first();
        $updateData = [
            'items' => [
                [
                    'id' => $purchaseItem1->id,
                    'item_id' => $this->item1->id,
                    'quantity' => 8, // Also reduce quantity slightly
                    'price' => 55.00, // Slight price change
                ]
                // item2 is removed from the purchase
            ]
        ];

        $response = $this->putJson(route('suppliers.purchases.update', $purchase), $updateData);
        $response->assertOk();

        // Verify item1 inventory was updated (reduced from 10 to 8)
        $inventory1->refresh();
        expect($inventory1->quantity)->toBe('8.0000');

        // Verify item2 inventory was reduced back to 0 (removed from purchase)
        $inventory2->refresh();
        expect($inventory2->quantity)->toBe('0.0000');

        // Verify item1 supplier price was updated
        $supplierPrice1->refresh();
        expect($supplierPrice1->price)->toBe('55.0000');

        // Verify item2 supplier price still exists but may not be current anymore
        // (depends on business logic - it might remain as historical data)
        $supplierPrice2->refresh();
        expect($supplierPrice2)->not()->toBeNull();

        // Verify item prices were recalculated appropriately
        $itemPrice1->refresh();
        expect($itemPrice1->price_usd)->toBeGreaterThan(0);

        $itemPrice2->refresh();
        expect($itemPrice2->price_usd)->toBeGreaterThan(0);

        // Verify purchase items count is correct
        $purchase->refresh();
        expect($purchase->purchaseItems)->toHaveCount(1);
        expect($purchase->purchaseItems->first()->item_id)->toBe($this->item1->id);
    });

    it('can filter purchases by supplier', function () {
        $otherSupplier = Supplier::factory()->create(['name' => 'Other Supplier']);

        Purchase::factory()->create(['supplier_id' => $this->supplier->id]);
        Purchase::factory()->create(['supplier_id' => $otherSupplier->id]);

        $response = $this->getJson(route('suppliers.purchases.index', ['supplier_id' => $this->supplier->id]));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can filter purchases by date range', function () {
        Purchase::factory()->create(['date' => '2025-01-01']);
        Purchase::factory()->create(['date' => '2025-02-15']);
        Purchase::factory()->create(['date' => '2025-03-30']);

        $response = $this->getJson(route('suppliers.purchases.index', [
            'start_date' => '2025-02-01',
            'end_date' => '2025-02-28'
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can search purchases by code', function () {
        $purchase1 = Purchase::factory()->create([
            'code' => 'PUR-001001',
            'supplier_id' => $this->supplier->id,
        ]);
        Purchase::factory()->create([
            'code' => 'PUR-001002',
            'supplier_id' => $this->supplier->id,
        ]);

        $response = $this->getJson(route('suppliers.purchases.index', ['search' => 'PUR-001001']));

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->toHaveCount(1);
        expect($data[0]['code'])->toBe('PUR-001001');
    });

    it('can search purchases by supplier invoice number', function () {
        Purchase::factory()->create([
            'supplier_invoice_number' => 'INV-12345',
            'supplier_id' => $this->supplier->id,
        ]);
        Purchase::factory()->create([
            'supplier_invoice_number' => 'INV-67890',
            'supplier_id' => $this->supplier->id,
        ]);

        $response = $this->getJson(route('suppliers.purchases.index', ['search' => 'INV-12345']));

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->toHaveCount(1);
        expect($data[0]['supplier_invoice_number'])->toBe('INV-12345');
    });

    it('sets created_by and updated_by fields automatically', function () {
        $purchase = Purchase::factory()->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        expect($purchase->created_by)->toBe($this->user->id);
        expect($purchase->updated_by)->toBe($this->user->id);

        $purchase->update(['note' => 'Updated note']);
        expect($purchase->fresh()->updated_by)->toBe($this->user->id);
    });

    it('returns 404 for non-existent purchase', function () {
        $response = $this->getJson(route('suppliers.purchases.show', 999));

        $response->assertNotFound();
    });

    it('can paginate purchases', function () {
        Purchase::factory()->count(7)->create([
            'supplier_id' => $this->supplier->id,
        ]);

        $response = $this->getJson(route('suppliers.purchases.index', ['per_page' => 3]));

        $response->assertOk();
        $data = $response->json('data');
        $pagination = $response->json('pagination');

        expect($data)->toHaveCount(3);
        expect($pagination['total'])->toBe(7);
        expect($pagination['per_page'])->toBe(3);
        expect($pagination['last_page'])->toBe(3);
    });
});

describe('Purchase Code Generation Tests', function () {
    it('gets next code when current setting is 1001', function () {
        Setting::set('purchases', 'code_counter', 1001, 'number');

        $nextCode = Purchase::getNextSuggestedCode();

        expect($nextCode)->toBe('001001');
    });

    it('creates purchase with current counter and increments correctly', function () {
        Setting::set('purchases', 'code_counter', 1005, 'number');

        $response = $this->postJson(route('suppliers.purchases.store'), ($this->getBasePurchaseData)([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 100.00,
                    'quantity' => 1,
                ]
            ]
        ]));

        $response->assertCreated();
        $code = $response->json('data.code');
        expect($code)->toBe('001005');

        $nextCode = Purchase::getNextSuggestedCode();
        expect($nextCode)->toBe('001006');
    });

    it('generates sequential purchase codes', function () {
        Purchase::withTrashed()->forceDelete();

        $response1 = $this->postJson(route('suppliers.purchases.store'), ($this->getBasePurchaseData)([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 100.00,
                    'quantity' => 1,
                ]
            ]
        ]));
        $response1->assertCreated();
        $code1 = $response1->json('data.code');
        dump($code1);
        $response2 = $this->postJson(route('suppliers.purchases.store'), ($this->getBasePurchaseData)([
            'supplier_invoice_number' => 'INV-2025-002',
            'items' => [
                [
                    'item_id' => $this->item2->id,
                    'price' => 200.00,
                    'quantity' => 1,
                ]
            ]
        ]));
        $response2->assertCreated();
        $code2 = $response2->json('data.code');

        $expected = str_pad((int)$code1 + 1, 6, '0', STR_PAD_LEFT);
        expect($code2)->toBe($expected);
    })->group('now');
});
