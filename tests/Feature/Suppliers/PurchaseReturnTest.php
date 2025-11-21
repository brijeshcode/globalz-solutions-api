<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Suppliers\PurchaseReturn;
use App\Models\Suppliers\PurchaseReturnItem;
use App\Models\Suppliers\Purchase;
use App\Models\Inventory\ItemPrice;
use App\Models\Inventory\ItemPriceHistory;
use App\Models\Inventory\Inventory;
use App\Models\Items\Item;
use App\Models\Setups\Supplier;
use App\Models\Setups\Warehouse;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\User;
use App\Models\Setting;

uses(RefreshDatabase::class)->group('api', 'suppliers', 'purchase-returns');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');

    // Create purchase return code counter setting
    Setting::create([
        'group_name' => 'purchase_returns',
        'key_name' => 'code_counter',
        'value' => '1000',
        'data_type' => 'number',
        'description' => 'Purchase return code counter starting from 1000'
    ]);

    // Create related models for testing
    $this->supplier = Supplier::factory()->create(['name' => 'Test Supplier']);
    $this->warehouse = Warehouse::factory()->create(['name' => 'Main Warehouse']);
    $this->currency = Currency::factory()->eur()->create(['is_active' => true]);

    // Create test items with weighted average cost calculation
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

    // Helper method for base purchase return data
    $this->getBasePurchaseReturnData = function ($overrides = []) {
        return array_merge([
            'date' => '2025-01-15',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'supplier_purchase_return_number' => 'RET-2025-001',
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
            'additional_charge_amount' => 0,
            'additional_charge_amount_usd' => 0,
        ], $overrides);
    };

    // Helper method to create purchase return via API
    $this->createPurchaseReturnViaApi = function ($overrides = []) {
        $purchaseReturnData = ($this->getBasePurchaseReturnData)($overrides);

        // Ensure items are provided if not in overrides
        if (!isset($purchaseReturnData['items'])) {
            $purchaseReturnData['items'] = [
                [
                    'item_id' => $this->item1->id,
                    'price' => 100.00,
                    'quantity' => 2,
                ]
            ];
        }

        $response = $this->postJson(route('suppliers.purchase-returns.store'), $purchaseReturnData);
        $response->assertCreated();

        $purchaseReturnId = $response->json('data.id');
        return PurchaseReturn::find($purchaseReturnId);
    };

    // Helper to setup initial inventory
    $this->setupInitialInventory = function (int $itemId, float $quantity, float $priceUsd) {
        Inventory::updateOrCreate(
            ['warehouse_id' => $this->warehouse->id, 'item_id' => $itemId],
            ['quantity' => $quantity]
        );

        ItemPrice::updateOrCreate(
            ['item_id' => $itemId],
            ['price_usd' => $priceUsd, 'effective_date' => now()->toDateString()]
        );
    };
});

describe('Purchase Returns API', function () {
    it('can list purchase returns', function () {
        PurchaseReturn::factory()->count(3)->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
        ]);

        $response = $this->getJson(route('suppliers.purchase-returns.index'));

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

    it('can create purchase return with items and reduces inventory', function () {
        // Setup initial inventory
        ($this->setupInitialInventory)($this->item1->id, 100, 50.00);
        ($this->setupInitialInventory)($this->item2->id, 200, 75.00);

        $purchaseReturnData = ($this->getBasePurchaseReturnData)([
            'shipping_fee_usd' => 25.00,
            'customs_fee_usd' => 10.00,
            'note' => 'Test purchase return with multiple items',
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 50.00,
                    'quantity' => 10,
                    'discount_percent' => 5,
                    'note' => 'First returned item'
                ],
                [
                    'item_id' => $this->item2->id,
                    'price' => 75.00,
                    'quantity' => 15,
                    'unit_discount_amount' => 5.00,
                    'note' => 'Second returned item'
                ]
            ]
        ]);

        $response = $this->postJson(route('suppliers.purchase-returns.store'), $purchaseReturnData);

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

        // Verify purchase return was created
        $this->assertDatabaseHas('purchase_returns', [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_purchase_return_number' => 'RET-2025-001',
        ]);

        // Verify purchase return items were created
        $purchaseReturn = PurchaseReturn::latest()->first();
        expect($purchaseReturn->purchaseReturnItems)->toHaveCount(2);

        // Verify inventory was reduced
        $inventory1 = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('item_id', $this->item1->id)
            ->first();
        expect($inventory1->quantity)->toBe('90.0000'); // 100 - 10

        $inventory2 = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('item_id', $this->item2->id)
            ->first();
        expect($inventory2->quantity)->toBe('185.0000'); // 200 - 15
    });

    it('can show purchase return with all relationships', function () {
        ($this->setupInitialInventory)($this->item1->id, 50, 30.00);

        $purchaseReturn = ($this->createPurchaseReturnViaApi)();

        $response = $this->getJson(route('suppliers.purchase-returns.show', $purchaseReturn));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'code',
                    'supplier' => ['id', 'name', 'code'],
                    'warehouse' => ['id', 'name'],
                    'currency' => ['id', 'name', 'code', 'symbol'],
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

    it('can update purchase return with item sync', function () {
        // Setup initial inventory
        ($this->setupInitialInventory)($this->item1->id, 100, 50.00);
        ($this->setupInitialInventory)($this->item2->id, 200, 75.00);

        // Create initial purchase return via API
        $purchaseReturn = ($this->createPurchaseReturnViaApi)([
            'currency_rate' => 1.0,
            'supplier_purchase_return_number' => 'RET-INITIAL',
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 5,
                    'price' => 50.00,
                ]
            ]
        ]);

        $existingItem = $purchaseReturn->purchaseReturnItems->first();

        // Update purchase return data
        $updateData = [
            'supplier_purchase_return_number' => 'RET-UPDATED',
            'currency_rate' => 1.15,
            'items' => [
                // Update existing item
                [
                    'id' => $existingItem->id,
                    'item_id' => $this->item1->id,
                    'price' => 55.00,
                    'quantity' => 8,
                    'note' => 'Updated item'
                ],
                // Add new item
                [
                    'item_id' => $this->item2->id,
                    'price' => 70.00,
                    'quantity' => 10,
                    'note' => 'New item added'
                ]
            ]
        ];

        $response = $this->putJson(route('suppliers.purchase-returns.update', $purchaseReturn), $updateData);

        $response->assertOk();

        // Verify purchase return was updated
        $purchaseReturn->refresh();
        expect($purchaseReturn->supplier_purchase_return_number)->toBe('RET-UPDATED');
        expect($purchaseReturn->currency_rate)->toBe('1.150000');

        // Verify items were synced
        expect($purchaseReturn->purchaseReturnItems)->toHaveCount(2);

        // Verify existing item was updated
        $existingItem->refresh();
        expect($existingItem->price)->toBe('55.0000');
        expect($existingItem->quantity)->toBe('8.0000');

        // Verify inventory was updated correctly
        // Initial: 100, first return: -5, update to 8: additional -3 = 92
        $inventory = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('item_id', $this->item1->id)
            ->first();
        expect($inventory->quantity)->toBe('92.0000');
    });

    it('auto-generates purchase return codes', function () {
        ($this->setupInitialInventory)($this->item1->id, 50, 30.00);

        $purchaseReturnData = ($this->getBasePurchaseReturnData)([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 30.00,
                    'quantity' => 5,
                ]
            ]
        ]);

        $response = $this->postJson(route('suppliers.purchase-returns.store'), $purchaseReturnData);

        $response->assertCreated();

        $purchaseReturn = PurchaseReturn::where('supplier_id', $this->supplier->id)->first();
        expect($purchaseReturn->code)->not()->toBeNull();
        expect($purchaseReturn->prefix)->toBe('PURTN');
    });

    it('can soft delete purchase return', function () {
        ($this->setupInitialInventory)($this->item1->id, 50, 30.00);

        $purchaseReturn = PurchaseReturn::factory()->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
        ]);

        $response = $this->deleteJson(route('suppliers.purchase-returns.destroy', $purchaseReturn));

        $response->assertStatus(204);
        $this->assertSoftDeleted('purchase_returns', ['id' => $purchaseReturn->id]);
    });

    it('can list trashed purchase returns', function () {
        ($this->setupInitialInventory)($this->item1->id, 50, 30.00);

        $purchaseReturn = PurchaseReturn::factory()->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
        ]);
        $purchaseReturn->delete();

        $response = $this->getJson(route('suppliers.purchase-returns.trashed'));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can restore trashed purchase return', function () {
        ($this->setupInitialInventory)($this->item1->id, 50, 30.00);

        $purchaseReturn = PurchaseReturn::factory()->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
        ]);
        $purchaseReturn->delete();

        $response = $this->patchJson(route('suppliers.purchase-returns.restore', $purchaseReturn->id));

        $response->assertOk();
        $this->assertDatabaseHas('purchase_returns', [
            'id' => $purchaseReturn->id,
            'deleted_at' => null
        ]);
    });

    it('can force delete purchase return', function () {
        ($this->setupInitialInventory)($this->item1->id, 50, 30.00);

        $purchaseReturn = PurchaseReturn::factory()->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
        ]);
        $purchaseReturn->delete();

        $response = $this->deleteJson(route('suppliers.purchase-returns.force-delete', $purchaseReturn->id));

        $response->assertStatus(204);
        $this->assertDatabaseMissing('purchase_returns', ['id' => $purchaseReturn->id]);
    });

    it('validates required fields when creating', function () {
        $invalidData = [
            'supplier_id' => 999, // Non-existent
            'warehouse_id' => null,
            'currency_id' => null,
            'items' => [
                [
                    'item_id' => 999, // Non-existent
                    'price' => -10, // Negative
                    'quantity' => 0, // Zero
                ]
            ]
        ];

        $response = $this->postJson(route('suppliers.purchase-returns.store'), $invalidData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'supplier_id',
                'items.0.item_id',
                'items.0.quantity'
            ]);
    });

    it('can filter purchase returns by supplier', function () {
        $otherSupplier = Supplier::factory()->create(['name' => 'Other Supplier']);

        PurchaseReturn::factory()->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
        ]);
        PurchaseReturn::factory()->create([
            'supplier_id' => $otherSupplier->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
        ]);

        $response = $this->getJson(route('suppliers.purchase-returns.index', ['supplier_id' => $this->supplier->id]));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can filter purchase returns by shipping status', function () {
        PurchaseReturn::factory()->create([
            'shipping_status' => 'Waiting',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
        ]);
        PurchaseReturn::factory()->create([
            'shipping_status' => 'Shipped',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
        ]);

        $response = $this->getJson(route('suppliers.purchase-returns.index', ['shipping_status' => 'Waiting']));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });
});

describe('Purchase Return Price Impact Tests', function () {
    it('reduces weighted average price when items are returned at higher cost', function () {
        // Setup: 150 units @ $10.67 weighted average
        ($this->setupInitialInventory)($this->item1->id, 150, 10.67);

        // Return 30 units that were purchased @ $12.00
        $purchaseReturnData = ($this->getBasePurchaseReturnData)([
            'currency_rate' => 1.0,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 12.00,
                    'quantity' => 30,
                ]
            ]
        ]);

        $response = $this->postJson(route('suppliers.purchase-returns.store'), $purchaseReturnData);
        $response->assertCreated();

        // Expected: (150 * 10.67 - 30 * 12.00) / (150 - 30) = (1600.5 - 360) / 120 = 10.34
        $itemPrice = ItemPrice::where('item_id', $this->item1->id)->first();
        expect(round((float) $itemPrice->price_usd, 2))->toBe(10.34);

        // Verify inventory was reduced
        $inventory = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('item_id', $this->item1->id)
            ->first();
        expect((float) $inventory->quantity)->toBe(120.0);

        // Verify price history was created
        $priceHistory = ItemPriceHistory::where('item_id', $this->item1->id)
            ->where('source_type', 'purchase_return')
            ->first();
        expect($priceHistory)->not()->toBeNull();
        expect($priceHistory->latest_price)->toBe('10.6700');
    });

    it('increases weighted average price when items are returned at lower cost', function () {
        // Setup: 100 units @ $15.00
        ($this->setupInitialInventory)($this->item1->id, 100, 15.00);

        // Return 20 units that were purchased @ $10.00
        $purchaseReturnData = ($this->getBasePurchaseReturnData)([
            'currency_rate' => 1.0,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 10.00,
                    'quantity' => 20,
                ]
            ]
        ]);

        $response = $this->postJson(route('suppliers.purchase-returns.store'), $purchaseReturnData);
        $response->assertCreated();

        // Expected: (100 * 15 - 20 * 10) / (100 - 20) = (1500 - 200) / 80 = 16.25
        $itemPrice = ItemPrice::where('item_id', $this->item1->id)->first();
        expect((float) $itemPrice->price_usd)->toBe(16.25);
    });

    it('does not affect price for COST_LAST_COST items', function () {
        // Setup: item2 uses COST_LAST_COST
        ($this->setupInitialInventory)($this->item2->id, 100, 50.00);

        $purchaseReturnData = ($this->getBasePurchaseReturnData)([
            'currency_rate' => 1.0,
            'items' => [
                [
                    'item_id' => $this->item2->id,
                    'price' => 55.00,
                    'quantity' => 20,
                ]
            ]
        ]);

        $response = $this->postJson(route('suppliers.purchase-returns.store'), $purchaseReturnData);
        $response->assertCreated();

        // Price should remain unchanged for COST_LAST_COST items
        $itemPrice = ItemPrice::where('item_id', $this->item2->id)->first();
        expect((float) $itemPrice->price_usd)->toBe(50.00);

        // But inventory should still be reduced
        $inventory = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('item_id', $this->item2->id)
            ->first();
        expect((float) $inventory->quantity)->toBe(80.0);
    });

    it('handles multiple return updates correctly', function () {
        // Setup initial state
        ($this->setupInitialInventory)($this->item1->id, 200, 12.00);

        // First return: 30 units @ $15.00
        $purchaseReturn = ($this->createPurchaseReturnViaApi)([
            'currency_rate' => 1.0,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 15.00,
                    'quantity' => 30,
                ]
            ]
        ]);

        // Expected after first return: (200 * 12 - 30 * 15) / (200 - 30) = (2400 - 450) / 170 = 11.47
        $itemPrice = ItemPrice::where('item_id', $this->item1->id)->first();
        expect(round((float) $itemPrice->price_usd, 2))->toBe(11.47);

        // Update return: change quantity from 30 to 50
        $returnItem = $purchaseReturn->purchaseReturnItems->first();
        $updateData = [
            'currency_rate' => 1.0,
            'items' => [
                [
                    'id' => $returnItem->id,
                    'item_id' => $this->item1->id,
                    'price' => 15.00,
                    'quantity' => 50, // Increased from 30
                ]
            ]
        ];

        $response = $this->putJson(route('suppliers.purchase-returns.update', $purchaseReturn), $updateData);
        $response->assertOk();

        // Expected after update: (200 * 12 - 50 * 15) / (200 - 50) = (2400 - 750) / 150 = 11.00
        $itemPrice->refresh();
        expect((float) $itemPrice->price_usd)->toBe(11.00);
    });
});

describe('Purchase Return Calculations', function () {
    it('calculates purchase return totals correctly', function () {
        ($this->setupInitialInventory)($this->item1->id, 100, 50.00);

        $purchaseReturnData = ($this->getBasePurchaseReturnData)([
            'currency_rate' => 0.5,
            'shipping_fee_usd' => 20.00,
            'customs_fee_usd' => 10.00,
            'additional_charge_amount_usd' => 5.00,
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'price' => 100.00,
                    'quantity' => 2,
                    'discount_percent' => 10, // 10% discount
                ]
            ]
        ]);

        $response = $this->postJson(route('suppliers.purchase-returns.store'), $purchaseReturnData);
        $response->assertCreated();

        $purchaseReturn = PurchaseReturn::latest()->first();

        // Verify calculations
        // Item total: (100 - 10) * 2 = 180.00
        // USD total: 180.00 * 0.5 = 90.00
        expect($purchaseReturn->sub_total)->toBe('180.0000');
        expect($purchaseReturn->sub_total_usd)->toBe('90.0000');

        // Final total USD: 90.00 + 5.00 + 20.00 + 10.00 = 125.00
        expect($purchaseReturn->final_total_usd)->toBe('125.0000');
    });

    it('sets created_by and updated_by fields automatically', function () {
        ($this->setupInitialInventory)($this->item1->id, 50, 30.00);

        $purchaseReturn = PurchaseReturn::factory()->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
        ]);

        expect($purchaseReturn->created_by)->toBe($this->user->id);
        expect($purchaseReturn->updated_by)->toBe($this->user->id);

        $purchaseReturn->update(['note' => 'Updated note']);
        expect($purchaseReturn->fresh()->updated_by)->toBe($this->user->id);
    });

    it('can paginate purchase returns', function () {
        PurchaseReturn::factory()->count(7)->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
        ]);

        $response = $this->getJson(route('suppliers.purchase-returns.index', ['per_page' => 3]));

        $response->assertOk();
        $data = $response->json('data');
        $pagination = $response->json('pagination');

        expect($data)->toHaveCount(3);
        expect($pagination['total'])->toBe(7);
        expect($pagination['per_page'])->toBe(3);
        expect($pagination['last_page'])->toBe(3);
    });
});
