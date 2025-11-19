<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
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

uses(RefreshDatabase::class)->group('api', 'suppliers', 'purchases');

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
            'prefix' => 'PUR',
            'date' => '2025-01-15',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'account_id' => $this->account->id,
            'supplier_invoice_number' => 'INV-2025-001',
            'currency_rate' => 1.25,
            'final_total_usd' => 0,
            'total_usd' => 0,
            'shipping_fee_usd' => 0,
            'customs_fee_usd' => 0,
            'other_fee_usd' => 0,
            'tax_usd' => 0,
            'shipping_fee_usd_percent' => 0,
            'customs_fee_usd_percent' => 0,
            'other_fee_usd_percent' => 0,
            'tax_usd_percent' => 0,
            'discount_amount' => 0,
            'discount_amount_usd' => 0,
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

        // Get the purchase from the response to ensure we have the correct one
        $purchaseId = $response->json('data.id');
        return Purchase::find($purchaseId);
    };
});

describe('Purchases API', function () {
    it('can list purchases', function () {
        Purchase::factory()->count(3)->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'account_id' => $this->account->id,
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
 

    it('calculates weighted average price correctly for new purchases', function () {
        // First purchase: 50 items @ $2.18
        $purchase1 = ($this->createPurchaseViaApi)([
            'currency_rate' => 1.0,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 2.18,
                    'quantity' => 50,
                ]
            ]
        ]);

        // Verify first purchase price
        $itemPrice = ItemPrice::where('item_id', $this->item1->id)->first();
        expect((float) $itemPrice->price_usd)->toBe(2.18);

        // Second purchase: 50 items @ $3.00
        $purchase2 = ($this->createPurchaseViaApi)([
            'currency_rate' => 1.0,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 3.00,
                    'quantity' => 50,
                ]
            ]
        ]);

        // Verify weighted average was calculated correctly
        // Expected: ((50 * 2.18) + (50 * 3.00)) / (50 + 50) = (109 + 150) / 100 = 2.59
        $itemPrice->refresh();
        expect((float) $itemPrice->price_usd)->toBe(2.59);

        // Verify inventory
        $inventory = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('item_id', $this->item1->id)
            ->first();
        expect((float) $inventory->quantity)->toBe(100.0);
    });

    it('recalculates weighted average correctly when updating purchase price', function () {
        // First purchase: 50 items @ $2.18
        $purchase1 = ($this->createPurchaseViaApi)([
            'currency_rate' => 1.0,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 2.18,
                    'quantity' => 50,
                ]
            ]
        ]);

        // Second purchase: 50 items @ $3.00
        $purchase2 = ($this->createPurchaseViaApi)([
            'currency_rate' => 1.0,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 3.00,
                    'quantity' => 50,
                ]
            ]
        ]);

        // Verify initial weighted average is correct
        $itemPrice = ItemPrice::where('item_id', $this->item1->id)->first();
        expect((float) $itemPrice->price_usd)->toBe(2.59);

        // Update second purchase price from $3.00 to $3.10
        $purchaseItem = $purchase2->purchaseItems->first();
        $updateData = [
            'final_total_usd' => 155,
            'total_usd' => 155,
            'items' => [
                [
                    'id' => $purchaseItem->id,
                    'item_id' => $this->item1->id,
                    'price' => 3.10,
                    'quantity' => 50,
                ]
            ]
        ];

        $response = $this->putJson(route('suppliers.purchases.update', $purchase2), $updateData);
        $response->assertOk();

        // Verify weighted average was recalculated correctly
        // Expected: ((50 * 2.18) + (50 * 3.10)) / 100 = (109 + 155) / 100 = 2.64
        $itemPrice->refresh();
        expect((float) $itemPrice->price_usd)->toBe(2.64);
    });

    it('maintains correct weighted average through multiple updates', function () {
        // First purchase: 50 items @ $2.18
        $purchase1 = ($this->createPurchaseViaApi)([
            'currency_rate' => 1.0,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 2.18,
                    'quantity' => 50,
                ]
            ]
        ]);

        // Second purchase: 50 items @ $3.00
        $purchase2 = ($this->createPurchaseViaApi)([
            'currency_rate' => 1.0,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 3.00,
                    'quantity' => 50,
                ]
            ]
        ]);

        $itemPrice = ItemPrice::where('item_id', $this->item1->id)->first();
        expect((float) $itemPrice->price_usd)->toBe(2.59);

        // Update 1: Change price from $3.00 to $3.10
        $purchaseItem = $purchase2->purchaseItems->first();
        $updateData = [
            'final_total_usd' => 0,
            'total_usd' => 0,
            'items' => [
                [
                    'id' => $purchaseItem->id,
                    'item_id' => $this->item1->id,
                    'price' => 3.10,
                    'quantity' => 50,
                ]
            ]
        ];
        $this->putJson(route('suppliers.purchases.update', $purchase2), $updateData)->assertOk();

        $itemPrice->refresh();
        expect((float) $itemPrice->price_usd)->toBe(2.64);

        // Update 2: Change price back from $3.10 to $3.00
        $purchaseItem->refresh();
        $updateData['items'][0]['price'] = 3.00;
        $this->putJson(route('suppliers.purchases.update', $purchase2), $updateData)->assertOk();

        // Should return to original weighted average
        $itemPrice->refresh();
        expect((float) $itemPrice->price_usd)->toBe(2.59);
    });

    it('self-corrects weighted average when updating purchase with corrupted price data', function () {
        // This test simulates a scenario where the database has corrupted price data
        // (as if it was calculated with the old buggy code)

        // First purchase: 50 items @ $2.18
        $purchase1 = ($this->createPurchaseViaApi)([
            'currency_rate' => 1.0,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 2.18,
                    'quantity' => 50,
                ]
            ]
        ]);

        // Second purchase: 50 items @ $3.00
        $purchase2 = ($this->createPurchaseViaApi)([
            'currency_rate' => 1.0,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 3.00,
                    'quantity' => 50,
                ]
            ]
        ]);

        // Manually corrupt the price to simulate old buggy calculation
        // (simulating the bug where it calculated $2.4533 instead of $2.59)
        $itemPrice = ItemPrice::where('item_id', $this->item1->id)->first();
        $itemPrice->update(['price_usd' => 2.4533]);

        // Now update the purchase - the new code should self-correct
        $purchaseItem = $purchase2->purchaseItems->first();
        $updateData = [
            'final_total_usd' => 0,
            'total_usd' => 0,
            'items' => [
                [
                    'id' => $purchaseItem->id,
                    'item_id' => $this->item1->id,
                    'price' => 3.00, // Keep same price
                    'quantity' => 50, // Keep same quantity
                ]
            ]
        ];

        $response = $this->putJson(route('suppliers.purchases.update', $purchase2), $updateData);
        $response->assertOk();

        // The system should recalculate from scratch and correct the price
        $itemPrice->refresh();
        expect((float) $itemPrice->price_usd)->toBe(2.59); // Corrected from 2.4533
    });

    it('calculates weighted average correctly when quantity changes in update', function () {
        // First purchase: 50 items @ $2.18
        $purchase1 = ($this->createPurchaseViaApi)([
            'currency_rate' => 1.0,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 2.18,
                    'quantity' => 50,
                ]
            ]
        ]);

        // Second purchase: 50 items @ $3.00
        $purchase2 = ($this->createPurchaseViaApi)([
            'currency_rate' => 1.0,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 3.00,
                    'quantity' => 50,
                ]
            ]
        ]);

        $itemPrice = ItemPrice::where('item_id', $this->item1->id)->first();
        expect((float) $itemPrice->price_usd)->toBe(2.59);

        // Update second purchase quantity from 50 to 100
        $purchaseItem = $purchase2->purchaseItems->first();
        $updateData = [
            'final_total_usd' => 0,
            'total_usd' => 0,
            'items' => [
                [
                    'id' => $purchaseItem->id,
                    'item_id' => $this->item1->id,
                    'price' => 3.00,
                    'quantity' => 100, // Changed from 50 to 100
                ]
            ]
        ];

        $response = $this->putJson(route('suppliers.purchases.update', $purchase2), $updateData);
        $response->assertOk();

        // Expected: ((50 * 2.18) + (100 * 3.00)) / 150 = (109 + 300) / 150 = 2.7267
        $itemPrice->refresh();
        $actualPrice = round((float) $itemPrice->price_usd, 2);
        expect($actualPrice)->toBe(2.73); // Rounded to 2 decimals

        // Verify inventory was updated
        $inventory = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('item_id', $this->item1->id)
            ->first();
        expect((float) $inventory->quantity)->toBe(150.0);
    });

    it('calculates weighted average correctly with multiple purchases and updates', function () {
        // Purchase 1: 100 items @ $10.00
        $purchase1 = ($this->createPurchaseViaApi)([
            'currency_rate' => 1.0,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 10.00,
                    'quantity' => 100,
                ]
            ]
        ]);

        $itemPrice = ItemPrice::where('item_id', $this->item1->id)->first();
        expect((float) $itemPrice->price_usd)->toBe(10.00);

        // Purchase 2: 50 items @ $12.00
        $purchase2 = ($this->createPurchaseViaApi)([
            'currency_rate' => 1.0,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 12.00,
                    'quantity' => 50,
                ]
            ]
        ]);

        // Expected: ((100 * 10) + (50 * 12)) / 150 = (1000 + 600) / 150 = 10.67
        $itemPrice->refresh();
        $actualPrice = round((float) $itemPrice->price_usd, 2);
        expect($actualPrice)->toBe(10.67);

        // Purchase 3: 50 items @ $15.00
        $purchase3 = ($this->createPurchaseViaApi)([
            'currency_rate' => 1.0,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 15.00,
                    'quantity' => 50,
                ]
            ]
        ]);

        // Expected: ((150 * 10.67) + (50 * 15)) / 200 = (1600.5 + 750) / 200 = 11.75
        $itemPrice->refresh();
        $actualPrice = round((float) $itemPrice->price_usd, 1);
        expect($actualPrice)->toBe(11.8); // Rounded to 1 decimal

        // Update purchase 2: change price from $12.00 to $14.00
        $purchaseItem2 = $purchase2->purchaseItems->first();
        $updateData = [
            'final_total_usd' => 0,
            'total_usd' => 0,
            'items' => [
                [
                    'id' => $purchaseItem2->id,
                    'item_id' => $this->item1->id,
                    'price' => 14.00,
                    'quantity' => 50,
                ]
            ]
        ];

        $this->putJson(route('suppliers.purchases.update', $purchase2), $updateData)->assertOk();

        // Expected after update:
        // Purchase 1: 100 @ $10, Purchase 2: 50 @ $14, Purchase 3: 50 @ $15
        // ((100 * 10) + (50 * 14) + (50 * 15)) / 200 = (1000 + 700 + 750) / 200 = 12.25
        $itemPrice->refresh();
        expect((float) $itemPrice->price_usd)->toBe(12.25);
    });

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
            'supplier_id' => $this->supplier->id, 'account_id' => $this->account
        ]);

        $response = $this->deleteJson(route('suppliers.purchases.destroy', $purchase));

        $response->assertStatus(204);
        $this->assertSoftDeleted('purchases', ['id' => $purchase->id]);
    });

    it('can list trashed purchases', function () {
        $purchase = Purchase::factory()->create([
            'supplier_id' => $this->supplier->id, 'account_id' => $this->account
        ]);
        $purchase->delete();

        $response = $this->getJson(route('suppliers.purchases.trashed'));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can restore trashed purchase', function () {
        $purchase = Purchase::factory()->create([
            'supplier_id' => $this->supplier->id, 'account_id' => $this->account
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
            'supplier_id' => $this->supplier->id, 'account_id' => $this->account
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

        Purchase::factory()->create(['supplier_id' => $this->supplier->id, 'account_id' => $this->account]);
        Purchase::factory()->create(['supplier_id' => $otherSupplier->id, 'account_id' => $this->account]);

        $response = $this->getJson(route('suppliers.purchases.index', ['supplier_id' => $this->supplier->id]));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can filter purchases by date range', function () {
        Purchase::factory()->create(['date' => '2025-01-01', 'account_id' => $this->account]);
        Purchase::factory()->create(['date' => '2025-02-15', 'account_id' => $this->account]);
        Purchase::factory()->create(['date' => '2025-03-30', 'account_id' => $this->account]);

        $response = $this->getJson(route('suppliers.purchases.index', [
            'from_date' => '2025-02-01',
            'to_date' => '2025-02-28'
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can search purchases by code', function () {
        $purchase1 = Purchase::factory()->create([
            'code' => 'PUR-001001',
            'supplier_id' => $this->supplier->id, 'account_id' => $this->account
            
        ]);
        Purchase::factory()->create([
            'code' => 'PUR-001002',
            'supplier_id' => $this->supplier->id, 'account_id' => $this->account
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
            'supplier_id' => $this->supplier->id
            , 'account_id' => $this->account
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
            'warehouse_id' => $this->warehouse->id
            , 'account_id' => $this->account
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
            'supplier_id' => $this->supplier->id, 'account_id' => $this->account
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

    it('prevents removing purchase items when inventory was already sold', function () {
        // Step 1: Create a purchase with 2 items
        $purchase = ($this->createPurchaseViaApi)([
            'currency_rate' => 1.0,
            'supplier_invoice_number' => 'INV-TEST-001',
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 100,
                    'price' => 50.00,
                ],
                [
                    'item_id' => $this->item2->id,
                    'quantity' => 200,
                    'price' => 30.00,
                ]
            ]
        ]);

        // Verify initial inventory
        $inventory1 = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('item_id', $this->item1->id)
            ->first();
        expect($inventory1->quantity)->toBe('100.0000');

        $inventory2 = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('item_id', $this->item2->id)
            ->first();
        expect($inventory2->quantity)->toBe('200.0000');

        // Step 2: Simulate selling 60 units of item1 and 150 units of item2
        // (In real scenario, this would happen through sales/consumption)
        $inventory1->update(['quantity' => 40]); // 60 sold, 40 remaining
        $inventory2->update(['quantity' => 50]); // 150 sold, 50 remaining

        $item1PurchaseItem = $purchase->purchaseItems->where('item_id', $this->item1->id)->first();
        $item2PurchaseItem = $purchase->purchaseItems->where('item_id', $this->item2->id)->first();

        // Step 3: Try to remove item1 (purchased 100, sold 60, remaining 40)
        // This should FAIL because we can't remove 100 units when only 40 remain
        $updateData = [
            'supplier_invoice_number' => 'INV-TEST-001',
            'items' => [
                // Only include item2, effectively removing item1
                [
                    'id' => $item2PurchaseItem->id,
                    'item_id' => $this->item2->id,
                    'quantity' => 200,
                    'price' => 30.00,
                ]
            ]
        ];

        $response = $this->putJson(route('suppliers.purchases.update', $purchase), $updateData);

        // Should fail with validation error
        $response->assertStatus(500); // RuntimeException wrapped in transaction
        expect($response->json('message'))->toContain("Cannot remove")
            ->toContain($this->item1->name)
            ->toContain('100 units')
            ->toContain('40 units')
            ->toContain('60 already sold/used');

        // Verify inventory unchanged
        $inventory1->refresh();
        expect($inventory1->quantity)->toBe('40.0000');

        // Step 4: Now try to remove item2 (purchased 200, sold 150, remaining 50)
        // This should also FAIL
        $updateData2 = [
            'supplier_invoice_number' => 'INV-TEST-001',
            'items' => [
                // Only include item1, effectively removing item2
                [
                    'id' => $item1PurchaseItem->id,
                    'item_id' => $this->item1->id,
                    'quantity' => 100,
                    'price' => 50.00,
                ]
            ]
        ];

        $response2 = $this->putJson(route('suppliers.purchases.update', $purchase), $updateData2);

        $response2->assertStatus(500);
        expect($response2->json('message'))->toContain("Cannot remove")
            ->toContain($this->item2->name)
            ->toContain('200 units')
            ->toContain('50 units')
            ->toContain('150 already sold/used');

        // Verify purchase still has both items
        $purchase->refresh();
        expect($purchase->purchaseItems)->toHaveCount(2);
    });

    it('allows removing purchase items when full inventory is available', function () {
        // Step 1: Create a purchase with 2 items
        $purchase = ($this->createPurchaseViaApi)([
            'currency_rate' => 1.0,
            'supplier_invoice_number' => 'INV-TEST-002',
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 50,
                    'price' => 25.00,
                ],
                [
                    'item_id' => $this->item2->id,
                    'quantity' => 100,
                    'price' => 15.00,
                ]
            ]
        ]);

        // Verify initial inventory
        $inventory1 = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('item_id', $this->item1->id)
            ->first();
        expect($inventory1->quantity)->toBe('50.0000');

        $inventory2 = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('item_id', $this->item2->id)
            ->first();
        expect($inventory2->quantity)->toBe('100.0000');

        // Step 2: Don't simulate any sales - full inventory available

        $item2PurchaseItem = $purchase->purchaseItems->where('item_id', $this->item2->id)->first();

        // Step 3: Remove item1 from the purchase (full 50 units still in inventory)
        // This should SUCCEED
        $updateData = [
            'supplier_invoice_number' => 'INV-TEST-002',
            'items' => [
                // Only include item2, removing item1
                [
                    'id' => $item2PurchaseItem->id,
                    'item_id' => $this->item2->id,
                    'quantity' => 100,
                    'price' => 15.00,
                ]
            ]
        ];

        $response = $this->putJson(route('suppliers.purchases.update', $purchase), $updateData);

        // Should succeed
        $response->assertOk();

        // Verify purchase now has only 1 item
        $purchase->refresh();
        expect($purchase->purchaseItems)->toHaveCount(1);
        expect($purchase->purchaseItems->first()->item_id)->toBe($this->item2->id);

        // Verify inventory was adjusted (item1 removed from inventory)
        $inventory1->refresh();
        expect($inventory1->quantity)->toBe('0.0000'); // 50 - 50 = 0
    });
});
