<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Suppliers\Purchase;
use App\Models\Suppliers\PurchaseItem;
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

uses(RefreshDatabase::class)->group('api', 'calculations', 'item-price');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');

    // Create purchase code counter setting
    Setting::create([
        'group_name' => 'purchases',
        'key_name' => 'code_counter',
        'value' => '1000',
        'data_type' => 'number',
        'description' => 'Purchase code counter'
    ]);

    // Create related models
    $this->supplier = Supplier::factory()->create(['name' => 'Test Supplier']);
    $this->warehouse1 = Warehouse::factory()->create(['name' => 'Warehouse 1']);
    $this->warehouse2 = Warehouse::factory()->create(['name' => 'Warehouse 2']);
    $this->currency = Currency::factory()->eur()->create(['is_active' => true]);
    $this->account = Account::factory()->create(['name' => 'Purchase Account']);

    // Helper method for base purchase data
    $this->getBasePurchaseData = function ($warehouseId = null, $overrides = []) {
        return array_merge([
            'prefix' => 'PUR',
            'date' => '2025-01-15',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $warehouseId ?? $this->warehouse1->id,
            'currency_id' => $this->currency->id,
            'account_id' => $this->account->id,
            'supplier_invoice_number' => 'INV-' . uniqid(),
            'currency_rate' => 1.0,
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

    // Helper method to create purchase via API
    $this->createPurchaseViaApi = function ($warehouseId = null, $overrides = []) {
        $purchaseData = ($this->getBasePurchaseData)($warehouseId, $overrides);

        $response = $this->postJson(route('suppliers.purchases.store'), $purchaseData);
        $response->assertCreated();

        $purchaseId = $response->json('data.id');
        return Purchase::find($purchaseId);
    };
});

describe('Item Creation Effects on Price', function () {
    it('creates item price when item is created with starting_price', function () {
        $item = Item::factory()->create([
            'short_name' => 'Test Item',
            'starting_price' => 50.00,
            'cost_calculation' => Item::COST_WEIGHTED_AVERAGE,
        ]);

        // Verify item price was created
        $itemPrice = ItemPrice::where('item_id', $item->id)->first();
        expect($itemPrice)->not()->toBeNull();
        expect((float) $itemPrice->price_usd)->toBe(50.00);

        // Verify price history was created
        $priceHistory = ItemPriceHistory::where('item_id', $item->id)->first();
        expect($priceHistory)->not()->toBeNull();
        expect((float) $priceHistory->price_usd)->toBe(50.00);
        expect($priceHistory->source_type)->toBe('initial');
        expect($priceHistory->note)->toContain('Initial price');
    });

    it('does not create item price when starting_price is zero', function () {
        $item = Item::factory()->create([
            'short_name' => 'Test Item No Price',
            'starting_price' => 0,
            'cost_calculation' => Item::COST_WEIGHTED_AVERAGE,
        ]);

        // Verify no item price was created
        $itemPrice = ItemPrice::where('item_id', $item->id)->first();
        expect($itemPrice)->toBeNull();

        // Verify no price history was created
        $priceHistory = ItemPriceHistory::where('item_id', $item->id)->first();
        expect($priceHistory)->toBeNull();
    });

    it('does not create item price when starting_price is not provided', function () {
        $item = Item::factory()->create([
            'short_name' => 'Test Item No Starting Price',
            'cost_calculation' => Item::COST_WEIGHTED_AVERAGE,
        ]);

        $itemPrice = ItemPrice::where('item_id', $item->id)->first();
        expect($itemPrice)->toBeNull();
    });
});

describe('Purchase Creation Effects on Price - LAST_COST Method', function () {
    it('sets item price to purchase price for first purchase with LAST_COST method', function () {
        $item = Item::factory()->create([
            'short_name' => 'LAST_COST Item',
            'cost_calculation' => Item::COST_LAST_COST,
        ]);

        $purchase = ($this->createPurchaseViaApi)(null, [
            'items' => [
                [
                    'item_id' => $item->id,
                    'price' => 100.00,
                    'quantity' => 10,
                ]
            ]
        ]);

        // Verify item price equals last purchase price
        $itemPrice = ItemPrice::where('item_id', $item->id)->first();
        expect($itemPrice)->not()->toBeNull();
        expect((float) $itemPrice->price_usd)->toBe(100.00);

        // Verify price history was created
        $priceHistory = ItemPriceHistory::where('item_id', $item->id)
            ->where('source_type', 'purchase')
            ->first();
        expect($priceHistory)->not()->toBeNull();
        expect((float) $priceHistory->price_usd)->toBe(100.00);
    });

    it('updates item price to new purchase price with LAST_COST method', function () {
        $item = Item::factory()->create([
            'short_name' => 'LAST_COST Item',
            'starting_price' => 50.00,
            'cost_calculation' => Item::COST_LAST_COST,
        ]);

        // First purchase at $75
        ($this->createPurchaseViaApi)(null, [
            'items' => [
                ['item_id' => $item->id, 'price' => 75.00, 'quantity' => 5]
            ]
        ]);

        $itemPrice = ItemPrice::where('item_id', $item->id)->first();
        expect((float) $itemPrice->price_usd)->toBe(75.00);

        // Second purchase at $90 - should replace the price
        ($this->createPurchaseViaApi)(null, [
            'items' => [
                ['item_id' => $item->id, 'price' => 90.00, 'quantity' => 3]
            ]
        ]);

        $itemPrice->refresh();
        expect((float) $itemPrice->price_usd)->toBe(90.00);
    });
});

describe('Purchase Creation Effects on Price - WEIGHTED_AVERAGE Method', function () {
    it('sets item price to purchase price for first purchase with WEIGHTED_AVERAGE', function () {
        $item = Item::factory()->create([
            'short_name' => 'WA Item',
            'cost_calculation' => Item::COST_WEIGHTED_AVERAGE,
        ]);

        $purchase = ($this->createPurchaseViaApi)(null, [
            'items' => [
                ['item_id' => $item->id, 'price' => 100.00, 'quantity' => 10]
            ]
        ]);

        $itemPrice = ItemPrice::where('item_id', $item->id)->first();
        expect($itemPrice)->not()->toBeNull();
        expect((float) $itemPrice->price_usd)->toBe(100.00);
    });

    it('calculates weighted average for second purchase', function () {
        $item = Item::factory()->create([
            'short_name' => 'WA Item',
            'cost_calculation' => Item::COST_WEIGHTED_AVERAGE,
        ]);

        // First purchase: 50 units @ $100
        ($this->createPurchaseViaApi)(null, [
            'items' => [
                ['item_id' => $item->id, 'price' => 100.00, 'quantity' => 50]
            ]
        ]);

        // Second purchase: 50 units @ $120
        ($this->createPurchaseViaApi)(null, [
            'items' => [
                ['item_id' => $item->id, 'price' => 120.00, 'quantity' => 50]
            ]
        ]);

        // Expected: ((50 * 100) + (50 * 120)) / 100 = 110
        $itemPrice = ItemPrice::where('item_id', $item->id)->first();
        expect((float) $itemPrice->price_usd)->toBe(110.00);
    });

    it('calculates weighted average with different quantities', function () {
        $item = Item::factory()->create([
            'short_name' => 'WA Item Different Qty',
            'cost_calculation' => Item::COST_WEIGHTED_AVERAGE,
        ]);

        // Purchase 1: 30 units @ $50
        ($this->createPurchaseViaApi)(null, [
            'items' => [
                ['item_id' => $item->id, 'price' => 50.00, 'quantity' => 30]
            ]
        ]);

        // Purchase 2: 70 units @ $70
        ($this->createPurchaseViaApi)(null, [
            'items' => [
                ['item_id' => $item->id, 'price' => 70.00, 'quantity' => 70]
            ]
        ]);

        // Expected: ((30 * 50) + (70 * 70)) / 100 = (1500 + 4900) / 100 = 64
        $itemPrice = ItemPrice::where('item_id', $item->id)->first();
        expect((float) $itemPrice->price_usd)->toBe(64.00);
    });

    it('calculates weighted average starting from initial price', function () {
        $item = Item::factory()->create([
            'short_name' => 'WA Item With Starting Price',
            'starting_price' => 80.00,
            'starting_quantity' => 20,
            'cost_calculation' => Item::COST_WEIGHTED_AVERAGE,
        ]);

        // Initialize inventory with starting quantity
        Inventory::create([
            'warehouse_id' => $this->warehouse1->id,
            'item_id' => $item->id,
            'quantity' => 20,
        ]);

        // New purchase: 30 units @ $100
        ($this->createPurchaseViaApi)(null, [
            'items' => [
                ['item_id' => $item->id, 'price' => 100.00, 'quantity' => 30]
            ]
        ]);

        // Expected: ((20 * 80) + (30 * 100)) / 50 = (1600 + 3000) / 50 = 92
        $itemPrice = ItemPrice::where('item_id', $item->id)->first();
        expect((float) $itemPrice->price_usd)->toBe(92.00);
    });
});

describe('Purchase Update Effects on Price', function () {
    it('recalculates weighted average when purchase price is updated', function () {
        $item = Item::factory()->create([
            'cost_calculation' => Item::COST_WEIGHTED_AVERAGE,
        ]);

        // Purchase 1: 50 @ $100
        $purchase1 = ($this->createPurchaseViaApi)(null, [
            'items' => [
                ['item_id' => $item->id, 'price' => 100.00, 'quantity' => 50]
            ]
        ]);

        // Purchase 2: 50 @ $120
        $purchase2 = ($this->createPurchaseViaApi)(null, [
            'items' => [
                ['item_id' => $item->id, 'price' => 120.00, 'quantity' => 50]
            ]
        ]);

        // Verify initial weighted average: 110
        $itemPrice = ItemPrice::where('item_id', $item->id)->first();
        expect((float) $itemPrice->price_usd)->toBe(110.00);

        // Update purchase 2 price from $120 to $140
        $purchaseItem = $purchase2->purchaseItems->first();
        $updateData = [
            'items' => [
                [
                    'id' => $purchaseItem->id,
                    'item_id' => $item->id,
                    'price' => 140.00,
                    'quantity' => 50,
                ]
            ]
        ];

        $response = $this->putJson(route('suppliers.purchases.update', $purchase2), $updateData);
        $response->assertOk();

        // Expected: ((50 * 100) + (50 * 140)) / 100 = 120
        $itemPrice->refresh();
        expect((float) $itemPrice->price_usd)->toBe(120.00);
    });

    it('recalculates weighted average when purchase quantity is updated', function () {
        $item = Item::factory()->create([
            'cost_calculation' => Item::COST_WEIGHTED_AVERAGE,
        ]);

        // Purchase 1: 40 @ $100
        $purchase1 = ($this->createPurchaseViaApi)(null, [
            'items' => [
                ['item_id' => $item->id, 'price' => 100.00, 'quantity' => 40]
            ]
        ]);

        // Purchase 2: 60 @ $120
        $purchase2 = ($this->createPurchaseViaApi)(null, [
            'items' => [
                ['item_id' => $item->id, 'price' => 120.00, 'quantity' => 60]
            ]
        ]);

        // Initial: ((40 * 100) + (60 * 120)) / 100 = 112
        $itemPrice = ItemPrice::where('item_id', $item->id)->first();
        expect((float) $itemPrice->price_usd)->toBe(112.00);

        // Update purchase 2 quantity from 60 to 80
        $purchaseItem = $purchase2->purchaseItems->first();
        $updateData = [
            'items' => [
                [
                    'id' => $purchaseItem->id,
                    'item_id' => $item->id,
                    'price' => 120.00,
                    'quantity' => 80,
                ]
            ]
        ];

        $this->putJson(route('suppliers.purchases.update', $purchase2), $updateData)->assertOk();

        // Expected: ((40 * 100) + (80 * 120)) / 120 = 113.33
        $itemPrice->refresh();
        $actualPrice = round((float) $itemPrice->price_usd, 2);
        expect($actualPrice)->toBe(113.33);
    });

    it('updates LAST_COST price when most recent purchase is updated', function () {
        $item = Item::factory()->create([
            'cost_calculation' => Item::COST_LAST_COST,
        ]);

        // Purchase 1: $100
        ($this->createPurchaseViaApi)(null, [
            'items' => [
                ['item_id' => $item->id, 'price' => 100.00, 'quantity' => 10]
            ]
        ]);

        // Purchase 2: $120 (most recent)
        $purchase2 = ($this->createPurchaseViaApi)(null, [
            'items' => [
                ['item_id' => $item->id, 'price' => 120.00, 'quantity' => 10]
            ]
        ]);

        $itemPrice = ItemPrice::where('item_id', $item->id)->first();
        expect((float) $itemPrice->price_usd)->toBe(120.00);

        // Update most recent purchase price to $150
        $purchaseItem = $purchase2->purchaseItems->first();
        $updateData = [
            'items' => [
                [
                    'id' => $purchaseItem->id,
                    'item_id' => $item->id,
                    'price' => 150.00,
                    'quantity' => 10,
                ]
            ]
        ];

        $this->putJson(route('suppliers.purchases.update', $purchase2), $updateData)->assertOk();

        $itemPrice->refresh();
        expect((float) $itemPrice->price_usd)->toBe(150.00);
    });

    it('self-corrects corrupted weighted average on update', function () {
        $item = Item::factory()->create([
            'cost_calculation' => Item::COST_WEIGHTED_AVERAGE,
        ]);

        // Purchase 1: 50 @ $100
        ($this->createPurchaseViaApi)(null, [
            'items' => [
                ['item_id' => $item->id, 'price' => 100.00, 'quantity' => 50]
            ]
        ]);

        // Purchase 2: 50 @ $120
        $purchase2 = ($this->createPurchaseViaApi)(null, [
            'items' => [
                ['item_id' => $item->id, 'price' => 120.00, 'quantity' => 50]
            ]
        ]);

        // Manually corrupt the price
        $itemPrice = ItemPrice::where('item_id', $item->id)->first();
        $itemPrice->update(['price_usd' => 999.99]);

        // Update purchase without changing values - should self-correct
        $purchaseItem = $purchase2->purchaseItems->first();
        $updateData = [
            'items' => [
                [
                    'id' => $purchaseItem->id,
                    'item_id' => $item->id,
                    'price' => 120.00,
                    'quantity' => 50,
                ]
            ]
        ];

        $this->putJson(route('suppliers.purchases.update', $purchase2), $updateData)->assertOk();

        // Should be corrected to: ((50 * 100) + (50 * 120)) / 100 = 110
        $itemPrice->refresh();
        expect((float) $itemPrice->price_usd)->toBe(110.00);
    });
});

describe('Purchase Deletion Effects on Price', function () {
    it('adjusts inventory when purchase item is removed from purchase', function () {
        $item = Item::factory()->create([
            'cost_calculation' => Item::COST_WEIGHTED_AVERAGE,
        ]);

        // Create purchase with item
        $purchase = ($this->createPurchaseViaApi)(null, [
            'items' => [
                ['item_id' => $item->id, 'price' => 100.00, 'quantity' => 50]
            ]
        ]);

        // Verify inventory
        $inventory = Inventory::where('item_id', $item->id)
            ->where('warehouse_id', $this->warehouse1->id)
            ->first();
        expect((float) $inventory->quantity)->toBe(50.0);

        // Update purchase to remove the item
        $purchaseItem = $purchase->purchaseItems->first();
        $updateData = [
            'items' => [] // Remove all items
        ];

        // This should fail validation (at least one item required)
        $response = $this->putJson(route('suppliers.purchases.update', $purchase), $updateData);
        $response->assertUnprocessable();
    });

    it('maintains price history when purchase is deleted', function () {
        $item = Item::factory()->create([
            'cost_calculation' => Item::COST_WEIGHTED_AVERAGE,
        ]);

        $purchase = ($this->createPurchaseViaApi)(null, [
            'items' => [
                ['item_id' => $item->id, 'price' => 100.00, 'quantity' => 10]
            ]
        ]);

        // Verify price history was created
        $historyCount = ItemPriceHistory::where('item_id', $item->id)
            ->where('source_type', 'purchase')
            ->count();
        expect($historyCount)->toBeGreaterThan(0);

        // Soft delete purchase
        $purchase->delete();

        // Price history should still exist (it's historical data)
        $historyAfterDelete = ItemPriceHistory::where('item_id', $item->id)
            ->where('source_type', 'purchase')
            ->count();
        expect($historyAfterDelete)->toBe($historyCount);
    });
});

describe('Multi-warehouse Price Calculations', function () {
    it('calculates global price across multiple warehouses for weighted average', function () {
        $item = Item::factory()->create([
            'cost_calculation' => Item::COST_WEIGHTED_AVERAGE,
        ]);

        // Purchase to Warehouse 1: 60 @ $100
        ($this->createPurchaseViaApi)($this->warehouse1->id, [
            'items' => [
                ['item_id' => $item->id, 'price' => 100.00, 'quantity' => 60]
            ]
        ]);

        $itemPrice = ItemPrice::where('item_id', $item->id)->first();
        expect((float) $itemPrice->price_usd)->toBe(100.00);

        // Purchase to Warehouse 2: 40 @ $130
        ($this->createPurchaseViaApi)($this->warehouse2->id, [
            'items' => [
                ['item_id' => $item->id, 'price' => 130.00, 'quantity' => 40]
            ]
        ]);

        // Expected global price: ((60 * 100) + (40 * 130)) / 100 = 112
        $itemPrice->refresh();
        expect((float) $itemPrice->price_usd)->toBe(112.00);

        // Verify both warehouses have correct inventory
        $inventory1 = Inventory::where('item_id', $item->id)
            ->where('warehouse_id', $this->warehouse1->id)
            ->first();
        expect((float) $inventory1->quantity)->toBe(60.0);

        $inventory2 = Inventory::where('item_id', $item->id)
            ->where('warehouse_id', $this->warehouse2->id)
            ->first();
        expect((float) $inventory2->quantity)->toBe(40.0);
    });

    it('uses global quantity for weighted average calculation regardless of warehouse', function () {
        $item = Item::factory()->create([
            'cost_calculation' => Item::COST_WEIGHTED_AVERAGE,
        ]);

        // Warehouse 1: 30 @ $50
        ($this->createPurchaseViaApi)($this->warehouse1->id, [
            'items' => [
                ['item_id' => $item->id, 'price' => 50.00, 'quantity' => 30]
            ]
        ]);

        // Warehouse 2: 20 @ $80
        ($this->createPurchaseViaApi)($this->warehouse2->id, [
            'items' => [
                ['item_id' => $item->id, 'price' => 80.00, 'quantity' => 20]
            ]
        ]);

        // Warehouse 1 again: 50 @ $70
        ($this->createPurchaseViaApi)($this->warehouse1->id, [
            'items' => [
                ['item_id' => $item->id, 'price' => 70.00, 'quantity' => 50]
            ]
        ]);

        // Expected: ((30 * 50) + (20 * 80) + (50 * 70)) / 100 = (1500 + 1600 + 3500) / 100 = 66
        $itemPrice = ItemPrice::where('item_id', $item->id)->first();
        expect((float) $itemPrice->price_usd)->toBe(66.00);
    });

    it('updates global price when purchase in any warehouse is updated', function () {
        $item = Item::factory()->create([
            'cost_calculation' => Item::COST_WEIGHTED_AVERAGE,
        ]);

        // Warehouse 1: 50 @ $100
        $purchase1 = ($this->createPurchaseViaApi)($this->warehouse1->id, [
            'items' => [
                ['item_id' => $item->id, 'price' => 100.00, 'quantity' => 50]
            ]
        ]);

        // Warehouse 2: 50 @ $120
        $purchase2 = ($this->createPurchaseViaApi)($this->warehouse2->id, [
            'items' => [
                ['item_id' => $item->id, 'price' => 120.00, 'quantity' => 50]
            ]
        ]);

        // Initial: 110
        $itemPrice = ItemPrice::where('item_id', $item->id)->first();
        expect((float) $itemPrice->price_usd)->toBe(110.00);

        // Update Warehouse 2 purchase to $140
        $purchaseItem = $purchase2->purchaseItems->first();
        $updateData = [
            'items' => [
                [
                    'id' => $purchaseItem->id,
                    'item_id' => $item->id,
                    'price' => 140.00,
                    'quantity' => 50,
                ]
            ]
        ];

        $this->putJson(route('suppliers.purchases.update', $purchase2), $updateData)->assertOk();

        // Expected: ((50 * 100) + (50 * 140)) / 100 = 120
        $itemPrice->refresh();
        expect((float) $itemPrice->price_usd)->toBe(120.00);
    });
});

describe('Price History Tracking', function () {
    it('creates price history entry for each price change', function () {
        $item = Item::factory()->create([
            'starting_price' => 50.00,
            'cost_calculation' => Item::COST_WEIGHTED_AVERAGE,
        ]);

        // Initial price history from item creation
        $historyCount = ItemPriceHistory::where('item_id', $item->id)->count();
        expect($historyCount)->toBe(1);

        // First purchase
        ($this->createPurchaseViaApi)(null, [
            'items' => [
                ['item_id' => $item->id, 'price' => 75.00, 'quantity' => 10]
            ]
        ]);

        $historyCount = ItemPriceHistory::where('item_id', $item->id)->count();
        expect($historyCount)->toBe(2);

        // Second purchase
        ($this->createPurchaseViaApi)(null, [
            'items' => [
                ['item_id' => $item->id, 'price' => 90.00, 'quantity' => 10]
            ]
        ]);

        $historyCount = ItemPriceHistory::where('item_id', $item->id)->count();
        expect($historyCount)->toBe(3);
    });

    it('stores correct source information in price history', function () {
        $item = Item::factory()->create([
            'cost_calculation' => Item::COST_WEIGHTED_AVERAGE,
        ]);

        $purchase = ($this->createPurchaseViaApi)(null, [
            'items' => [
                ['item_id' => $item->id, 'price' => 100.00, 'quantity' => 10]
            ]
        ]);

        $priceHistory = ItemPriceHistory::where('item_id', $item->id)
            ->where('source_type', 'purchase')
            ->first();

        expect($priceHistory)->not()->toBeNull();
        expect($priceHistory->source_type)->toBe('purchase');
        expect($priceHistory->source_id)->toBe($purchase->id);
        expect($priceHistory->note)->toContain('Purchase #' . $purchase->id);
    });

    it('tracks old and new prices in history', function () {
        $item = Item::factory()->create([
            'starting_price' => 50.00,
            'cost_calculation' => Item::COST_LAST_COST,
        ]);

        // First purchase
        ($this->createPurchaseViaApi)(null, [
            'items' => [
                ['item_id' => $item->id, 'price' => 75.00, 'quantity' => 10]
            ]
        ]);

        $priceHistory = ItemPriceHistory::where('item_id', $item->id)
            ->where('source_type', 'purchase')
            ->orderBy('created_at', 'desc')
            ->first();

        expect((float) $priceHistory->latest_price)->toBe(50.00); // Old price
        expect((float) $priceHistory->price_usd)->toBe(75.00); // New price
    });
});

describe('Edge Cases and Special Scenarios', function () {
    it('handles zero quantity updates correctly', function () {
        $item = Item::factory()->create([
            'cost_calculation' => Item::COST_WEIGHTED_AVERAGE,
        ]);

        $purchase = ($this->createPurchaseViaApi)(null, [
            'items' => [
                ['item_id' => $item->id, 'price' => 100.00, 'quantity' => 10]
            ]
        ]);

        // Try to update quantity to 0 (should fail validation)
        $purchaseItem = $purchase->purchaseItems->first();
        $updateData = [
            'items' => [
                [
                    'id' => $purchaseItem->id,
                    'item_id' => $item->id,
                    'price' => 100.00,
                    'quantity' => 0,
                ]
            ]
        ];

        $response = $this->putJson(route('suppliers.purchases.update', $purchase), $updateData);
        $response->assertUnprocessable();
    });

    it('maintains price accuracy with multiple rapid updates', function () {
        $item = Item::factory()->create([
            'cost_calculation' => Item::COST_WEIGHTED_AVERAGE,
        ]);

        // Purchase 1: 50 @ $100
        ($this->createPurchaseViaApi)(null, [
            'items' => [
                ['item_id' => $item->id, 'price' => 100.00, 'quantity' => 50]
            ]
        ]);

        // Purchase 2: 50 @ $120
        $purchase2 = ($this->createPurchaseViaApi)(null, [
            'items' => [
                ['item_id' => $item->id, 'price' => 120.00, 'quantity' => 50]
            ]
        ]);

        $purchaseItem = $purchase2->purchaseItems->first();

        // Rapid update 1
        $this->putJson(route('suppliers.purchases.update', $purchase2), [
            'items' => [
                ['id' => $purchaseItem->id, 'item_id' => $item->id, 'price' => 125.00, 'quantity' => 50]
            ]
        ])->assertOk();

        // Rapid update 2
        $this->putJson(route('suppliers.purchases.update', $purchase2), [
            'items' => [
                ['id' => $purchaseItem->id, 'item_id' => $item->id, 'price' => 130.00, 'quantity' => 50]
            ]
        ])->assertOk();

        // Rapid update 3
        $this->putJson(route('suppliers.purchases.update', $purchase2), [
            'items' => [
                ['id' => $purchaseItem->id, 'item_id' => $item->id, 'price' => 120.00, 'quantity' => 50]
            ]
        ])->assertOk();

        // Final price should be: ((50 * 100) + (50 * 120)) / 100 = 110
        $itemPrice = ItemPrice::where('item_id', $item->id)->first();
        expect((float) $itemPrice->price_usd)->toBe(110.00);
    });

    it('does not create price history when price does not change', function () {
        $item = Item::factory()->create([
            'starting_price' => 100.00,
            'cost_calculation' => Item::COST_LAST_COST,
        ]);

        $initialHistoryCount = ItemPriceHistory::where('item_id', $item->id)->count();

        // Purchase at same price
        ($this->createPurchaseViaApi)(null, [
            'items' => [
                ['item_id' => $item->id, 'price' => 100.00, 'quantity' => 10]
            ]
        ]);

        // Should not create new history entry since price didn't change
        $newHistoryCount = ItemPriceHistory::where('item_id', $item->id)->count();
        expect($newHistoryCount)->toBe($initialHistoryCount); // No new entry
    });
});
