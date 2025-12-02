<?php

/**
 * here we need to test if items quantity is been updating correctly or not
 * for that we need to check at following points :
 * 1. item module : when we add new item we have option to add the quantity in the system so test if inventory added or not
 * 2. purchase module: when we make new purchase, update purchase, delete purchase
 * 2a. purchase return module: when we make new purchase return, update purchase return, delete purchase return
 * 3. sale module: when we make new sale, update sale, delete sale
 * 3a. sale return module: when we make new sale return, update sale return, delete sale return
 * 4. stock adjust: add and subtract adjustments
 * 5. stock transfer: transfer inventory between warehouses
 * points to note that we have multiple warehouses
 *
 * finally we need to check after all above transactions the inventory table values should be correct
 * in this test we only focus on item quantity, inventory not on price
 *
 */

use App\Models\Customers\Customer;
use App\Models\Inventory\Inventory;
use App\Models\Items\Item;
use App\Models\Setting;
use App\Models\Setups\ItemFamily;
use App\Models\Setups\ItemType;
use App\Models\Setups\ItemUnit;
use App\Models\Setups\Supplier;
use App\Models\Setups\TaxCode;
use App\Models\Setups\Warehouse;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\User;

uses()->group('calculations', 'inventory', 'item-quantity-movement');

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->user, 'sanctum');

    // Create settings
    Setting::create([
        'group_name' => 'items',
        'key_name' => 'code_counter',
        'value' => '5000',
        'data_type' => 'number',
        'description' => 'Item code counter'
    ]);

    Setting::create([
        'group_name' => 'purchases',
        'key_name' => 'code_counter',
        'value' => '1000',
        'data_type' => 'number',
        'description' => 'Purchase code counter'
    ]);

    Setting::create([
        'group_name' => 'sales',
        'key_name' => 'code_counter',
        'value' => '1000',
        'data_type' => 'number',
        'description' => 'Sale code counter'
    ]);

    // Create warehouses
    $this->warehouse1 = Warehouse::factory()->create(['name' => 'Warehouse 1', 'is_default' => true, 'is_active' => true]);
    $this->warehouse2 = Warehouse::factory()->create(['name' => 'Warehouse 2', 'is_default' => false , 'is_active' => true]);

    // Create related models
    $this->itemType = ItemType::factory()->create(['is_active' => true]);
    $this->itemFamily = ItemFamily::factory()->create(['is_active' => true]);
    $this->itemUnit = ItemUnit::factory()->create(['is_active' => true]);
    $this->taxCode = TaxCode::factory()->create(['tax_percent' => 0, 'is_active' => true]);
    $this->supplier = Supplier::factory()->create(['is_active' => true]);
    $this->customer = Customer::factory()->create(['is_active' => true]);
    $this->currency = Currency::factory()->create(['code' => 'USD', 'symbol' => '$', 'is_active' => true]);

    // Helper to get inventory quantity
    $this->getInventoryQuantity = function ($itemId, $warehouseId) {
        $inventory = Inventory::where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->first();
        return $inventory ? (float) $inventory->quantity : 0;
    };
});

describe('1. Item Module - Starting Quantity via API', function () {

    it('creates inventory record when item is created via API with starting_quantity', function () {
        $response = $this->postJson(route('setups.items.store'), [
            'short_name' => 'Test Item',
            'description' => 'Test item description',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
            'tax_code_id' => $this->taxCode->id,
            'base_cost' => 100.00,
            'base_sell' => 120.00,
            'starting_quantity' => 100,
            'is_active' => true,
        ]);

        $response->assertCreated();
        $itemId = $response->json('data.id');

        $inventory = Inventory::where('item_id', $itemId)
            ->where('warehouse_id', $this->warehouse1->id)
            ->first();

        expect($inventory)->not()->toBeNull();
        expect((float) $inventory->quantity)->toBe(100.0);
    });

    it('does not create inventory when starting_quantity is zero via API', function () {
        $response = $this->postJson(route('setups.items.store'), [
            'short_name' => 'Test Item',
            'description' => 'Test item description',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
            'tax_code_id' => $this->taxCode->id,
            'base_cost' => 100.00,
            'base_sell' => 120.00,
            'starting_quantity' => 0,
            'is_active' => true,
        ]);

        $response->assertCreated();
        $itemId = $response->json('data.id');

        $inventory = Inventory::where('item_id', $itemId)->first();
        expect($inventory)->toBeNull();
    });
});

describe('2. Purchase Module - Inventory Updates via API', function () {

    it('increases inventory when purchase is created via API', function () {
        // Create item first
        $item = Item::factory()->create([
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
            'tax_code_id' => $this->taxCode->id,
            'starting_quantity' => 0,
        ]);

        // Create purchase via API
        $response = $this->postJson(route('suppliers.purchases.store'), [
            'date' => now()->format('Y-m-d'),
            'prefix' => 'PUR',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse1->id,
            'currency_id' => $this->currency->id,
            'currency_rate' => 1.0,
            'sub_total' => 1000,
            'sub_total_usd' => 1000,
            'discount_amount' => 0,
            'discount_amount_usd' => 0,
            'total' => 1000,
            'total_usd' => 1000,
            'final_total' => 1000,
            'final_total_usd' => 1000,
            'shipping_fee_usd' => 0,
            'customs_fee_usd' => 0,
            'other_fee_usd' => 0,
            'tax_usd' => 0,
            'items' => [
                [
                    'item_id' => $item->id,
                    'price' => 10.00,
                    'quantity' => 100,
                    'discount_percent' => 0,
                    'discount_amount' => 0,
                ]
            ]
        ]);

        $response->assertCreated();

        $quantity = ($this->getInventoryQuantity)($item->id, $this->warehouse1->id);
        expect($quantity)->toBe(100.0);
    });

    it('handles multiple items in a single purchase via API', function () {
        // Create first item via API
        $item1Response = $this->postJson(route('setups.items.store'), [
            'short_name' => 'Multi Purchase Item 1',
            'description' => 'First item for multi-purchase test',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
            'tax_code_id' => $this->taxCode->id,
            'base_cost' => 80.00,
            'base_sell' => 100.00,
            'starting_quantity' => 50,
            'is_active' => true,
        ]);
        $item1Response->assertCreated();
        $item1Id = $item1Response->json('data.id');

        // Create second item via API
        $item2Response = $this->postJson(route('setups.items.store'), [
            'short_name' => 'Multi Purchase Item 2',
            'description' => 'Second item for multi-purchase test',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
            'tax_code_id' => $this->taxCode->id,
            'base_cost' => 80.00,
            'base_sell' => 100.00,
            'starting_quantity' => 25,
            'is_active' => true,
        ]);
        $item2Response->assertCreated();
        $item2Id = $item2Response->json('data.id');

        $response = $this->postJson(route('suppliers.purchases.store'), [
            'date' => now()->format('Y-m-d'),
            'prefix' => 'PUR',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse1->id,
            'currency_id' => $this->currency->id,
            'currency_rate' => 1.0,
            'sub_total' => 2500,
            'sub_total_usd' => 2500,
            'discount_amount' => 0,
            'discount_amount_usd' => 0,
            'total' => 2500,
            'total_usd' => 2500,
            'final_total' => 2500,
            'final_total_usd' => 2500,
            'shipping_fee_usd' => 0,
            'customs_fee_usd' => 0,
            'other_fee_usd' => 0,
            'tax_usd' => 0,
            'items' => [
                [
                    'item_id' => $item1Id,
                    'price' => 10.00,
                    'quantity' => 100,
                    'discount_percent' => 0,
                    'discount_amount' => 0,
                ],
                [
                    'item_id' => $item2Id,
                    'price' => 15.00,
                    'quantity' => 100,
                    'discount_percent' => 0,
                    'discount_amount' => 0,
                ]
            ]
        ]);

        $response->assertCreated();

        expect(($this->getInventoryQuantity)($item1Id, $this->warehouse1->id))->toBe(150.0); // 50 + 100
        expect(($this->getInventoryQuantity)($item2Id, $this->warehouse1->id))->toBe(125.0); // 25 + 100
    });

    it('updates inventory when purchase is updated via API', function () {
        // Create item first
        $item = Item::factory()->create([
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
            'tax_code_id' => $this->taxCode->id,
            'starting_quantity' => 0,
        ]);

        // Create purchase via API
        $response = $this->postJson(route('suppliers.purchases.store'), [
            'date' => now()->format('Y-m-d'),
            'prefix' => 'PUR',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse1->id,
            'currency_id' => $this->currency->id,
            'currency_rate' => 1.0,
            'sub_total' => 1000,
            'sub_total_usd' => 1000,
            'discount_amount' => 0,
            'discount_amount_usd' => 0,
            'total' => 1000,
            'total_usd' => 1000,
            'final_total' => 1000,
            'final_total_usd' => 1000,
            'shipping_fee_usd' => 0,
            'customs_fee_usd' => 0,
            'other_fee_usd' => 0,
            'tax_usd' => 0,
            'items' => [
                [
                    'item_id' => $item->id,
                    'price' => 10.00,
                    'quantity' => 100,
                    'discount_percent' => 0,
                    'discount_amount' => 0,
                ]
            ]
        ]);

        $response->assertCreated();
        $purchaseId = $response->json('data.id');
        $purchaseItemId = $response->json('data.items.0.id');

        expect(($this->getInventoryQuantity)($item->id, $this->warehouse1->id))->toBe(100.0);

        // Update purchase to change quantity to 200
        $updateResponse = $this->putJson(route('suppliers.purchases.update', $purchaseId), [
            'date' => now()->format('Y-m-d'),
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse1->id,
            'currency_id' => $this->currency->id,
            'currency_rate' => 1.0,
            'sub_total' => 2000,
            'sub_total_usd' => 2000,
            'discount_amount' => 0,
            'discount_amount_usd' => 0,
            'total' => 2000,
            'total_usd' => 2000,
            'final_total' => 2000,
            'final_total_usd' => 2000,
            'shipping_fee_usd' => 0,
            'customs_fee_usd' => 0,
            'other_fee_usd' => 0,
            'tax_usd' => 0,
            'items' => [
                [
                    'id' => $purchaseItemId,
                    'item_id' => $item->id,
                    'price' => 10.00,
                    'quantity' => 200,
                    'discount_percent' => 0,
                    'discount_amount' => 0,
                ]
            ]
        ]);

        $updateResponse->assertOk();

        $quantity = ($this->getInventoryQuantity)($item->id, $this->warehouse1->id);
        expect($quantity)->toBe(200.0); // Should be updated to 200
    });

    it('restores inventory when purchase is deleted via API', function () {
        // Create item first
        $item = Item::factory()->create([
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
            'tax_code_id' => $this->taxCode->id,
            'starting_quantity' => 0,
        ]);

        // Create purchase via API
        $response = $this->postJson(route('suppliers.purchases.store'), [
            'date' => now()->format('Y-m-d'),
            'prefix' => 'PUR',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse1->id,
            'currency_id' => $this->currency->id,
            'currency_rate' => 1.0,
            'sub_total' => 1500,
            'sub_total_usd' => 1500,
            'discount_amount' => 0,
            'discount_amount_usd' => 0,
            'total' => 1500,
            'total_usd' => 1500,
            'final_total' => 1500,
            'final_total_usd' => 1500,
            'shipping_fee_usd' => 0,
            'customs_fee_usd' => 0,
            'other_fee_usd' => 0,
            'tax_usd' => 0,
            'items' => [
                [
                    'item_id' => $item->id,
                    'price' => 10.00,
                    'quantity' => 150,
                    'discount_percent' => 0,
                    'discount_amount' => 0,
                ]
            ]
        ]);

        $response->assertCreated();
        $purchaseId = $response->json('data.id');

        expect(($this->getInventoryQuantity)($item->id, $this->warehouse1->id))->toBe(150.0);

        // Delete purchase
        $deleteResponse = $this->deleteJson(route('suppliers.purchases.destroy', $purchaseId));
        $deleteResponse->assertNoContent();

        $quantity = ($this->getInventoryQuantity)($item->id, $this->warehouse1->id);
        expect($quantity)->toBe(0.0); // Should be back to 0
    });
});

describe('2a. Purchase Return Module - Inventory Updates via API', function () {

    it('decreases inventory when purchase return is created via API', function () {
        // Create item first
        $item = Item::factory()->create([
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
            'tax_code_id' => $this->taxCode->id,
            'starting_quantity' => 0,
        ]);

        // Create purchase first to have inventory
        $purchaseResponse = $this->postJson(route('suppliers.purchases.store'), [
            'date' => now()->format('Y-m-d'),
            'prefix' => 'PUR',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse1->id,
            'currency_id' => $this->currency->id,
            'currency_rate' => 1.0,
            'sub_total' => 2000,
            'sub_total_usd' => 2000,
            'discount_amount' => 0,
            'discount_amount_usd' => 0,
            'total' => 2000,
            'total_usd' => 2000,
            'final_total' => 2000,
            'final_total_usd' => 2000,
            'shipping_fee_usd' => 0,
            'customs_fee_usd' => 0,
            'other_fee_usd' => 0,
            'tax_usd' => 0,
            'items' => [
                [
                    'item_id' => $item->id,
                    'price' => 10.00,
                    'quantity' => 200,
                    'discount_percent' => 0,
                    'discount_amount' => 0,
                ]
            ]
        ]);

        $purchaseResponse->assertCreated();
        expect(($this->getInventoryQuantity)($item->id, $this->warehouse1->id))->toBe(200.0);

        // Create purchase return via API
        $response = $this->postJson(route('suppliers.purchase-returns.store'), [
            'date' => now()->format('Y-m-d'),
            'prefix' => 'PR',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse1->id,
            'currency_id' => $this->currency->id,
            'currency_rate' => 1.0,
            'sub_total' => 500,
            'sub_total_usd' => 500,
            'discount_amount' => 0,
            'discount_amount_usd' => 0,
            'total' => 500,
            'total_usd' => 500,
            'final_total' => 500,
            'final_total_usd' => 500,
            'items' => [
                [
                    'item_id' => $item->id,
                    'price' => 10.00,
                    'quantity' => 50,
                    'discount_percent' => 0,
                    'discount_amount' => 0,
                ]
            ]
        ]);

        $response->assertCreated();

        $quantity = ($this->getInventoryQuantity)($item->id, $this->warehouse1->id);
        expect($quantity)->toBe(150.0); // 200 - 50
    });

    it('updates inventory when purchase return is updated via API', function () {
        // Create item first
        $item = Item::factory()->create([
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
            'tax_code_id' => $this->taxCode->id,
            'starting_quantity' => 0,
        ]);

        // Create purchase first to have inventory
        $purchaseResponse = $this->postJson(route('suppliers.purchases.store'), [
            'date' => now()->format('Y-m-d'),
            'prefix' => 'PUR',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse1->id,
            'currency_id' => $this->currency->id,
            'currency_rate' => 1.0,
            'sub_total' => 2000,
            'sub_total_usd' => 2000,
            'discount_amount' => 0,
            'discount_amount_usd' => 0,
            'total' => 2000,
            'total_usd' => 2000,
            'final_total' => 2000,
            'final_total_usd' => 2000,
            'shipping_fee_usd' => 0,
            'customs_fee_usd' => 0,
            'other_fee_usd' => 0,
            'tax_usd' => 0,
            'items' => [
                [
                    'item_id' => $item->id,
                    'price' => 10.00,
                    'quantity' => 200,
                    'discount_percent' => 0,
                    'discount_amount' => 0,
                ]
            ]
        ]);

        $purchaseResponse->assertCreated();

        // Create purchase return
        $response = $this->postJson(route('suppliers.purchase-returns.store'), [
            'date' => now()->format('Y-m-d'),
            'prefix' => 'PR',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse1->id,
            'currency_id' => $this->currency->id,
            'currency_rate' => 1.0,
            'sub_total' => 300,
            'sub_total_usd' => 300,
            'discount_amount' => 0,
            'discount_amount_usd' => 0,
            'total' => 300,
            'total_usd' => 300,
            'final_total' => 300,
            'final_total_usd' => 300,
            'items' => [
                [
                    'item_id' => $item->id,
                    'price' => 10.00,
                    'quantity' => 30,
                    'discount_percent' => 0,
                    'discount_amount' => 0,
                ]
            ]
        ]);

        $response->assertCreated();
        $returnId = $response->json('data.id');
        $returnItemId = $response->json('data.items.0.id');

        expect(($this->getInventoryQuantity)($item->id, $this->warehouse1->id))->toBe(170.0); // 200 - 30

        // Update purchase return to increase return quantity to 70
        $updateResponse = $this->putJson(route('suppliers.purchase-returns.update', $returnId), [
            'date' => now()->format('Y-m-d'),
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse1->id,
            'currency_id' => $this->currency->id,
            'currency_rate' => 1.0,
            'sub_total' => 700,
            'sub_total_usd' => 700,
            'discount_amount' => 0,
            'discount_amount_usd' => 0,
            'total' => 700,
            'total_usd' => 700,
            'final_total' => 700,
            'final_total_usd' => 700,
            'items' => [
                [
                    'id' => $returnItemId,
                    'item_id' => $item->id,
                    'price' => 10.00,
                    'quantity' => 70,
                    'discount_percent' => 0,
                    'discount_amount' => 0,
                ]
            ]
        ]);

        $updateResponse->assertOk();

        $quantity = ($this->getInventoryQuantity)($item->id, $this->warehouse1->id);
        expect($quantity)->toBe(130.0); // 200 - 70
    });

    it('restores inventory when purchase return is deleted via API', function () {
        // Create item first
        $item = Item::factory()->create([
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
            'tax_code_id' => $this->taxCode->id,
            'starting_quantity' => 0,
        ]);

        // Create purchase first to have inventory
        $purchaseResponse = $this->postJson(route('suppliers.purchases.store'), [
            'date' => now()->format('Y-m-d'),
            'prefix' => 'PUR',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse1->id,
            'currency_id' => $this->currency->id,
            'currency_rate' => 1.0,
            'sub_total' => 2000,
            'sub_total_usd' => 2000,
            'discount_amount' => 0,
            'discount_amount_usd' => 0,
            'total' => 2000,
            'total_usd' => 2000,
            'final_total' => 2000,
            'final_total_usd' => 2000,
            'shipping_fee_usd' => 0,
            'customs_fee_usd' => 0,
            'other_fee_usd' => 0,
            'tax_usd' => 0,
            'items' => [
                [
                    'item_id' => $item->id,
                    'price' => 10.00,
                    'quantity' => 200,
                    'discount_percent' => 0,
                    'discount_amount' => 0,
                ]
            ]
        ]);

        $purchaseResponse->assertCreated();

        // Create purchase return
        $response = $this->postJson(route('suppliers.purchase-returns.store'), [
            'date' => now()->format('Y-m-d'),
            'prefix' => 'PR',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse1->id,
            'currency_id' => $this->currency->id,
            'currency_rate' => 1.0,
            'sub_total' => 600,
            'sub_total_usd' => 600,
            'discount_amount' => 0,
            'discount_amount_usd' => 0,
            'total' => 600,
            'total_usd' => 600,
            'final_total' => 600,
            'final_total_usd' => 600,
            'items' => [
                [
                    'item_id' => $item->id,
                    'price' => 10.00,
                    'quantity' => 60,
                    'discount_percent' => 0,
                    'discount_amount' => 0,
                ]
            ]
        ]);

        $response->assertCreated();
        $returnId = $response->json('data.id');

        expect(($this->getInventoryQuantity)($item->id, $this->warehouse1->id))->toBe(140.0); // 200 - 60

        // Delete purchase return
        $deleteResponse = $this->deleteJson(route('suppliers.purchase-returns.destroy', $returnId));
        $deleteResponse->assertNoContent();

        $quantity = ($this->getInventoryQuantity)($item->id, $this->warehouse1->id);
        expect($quantity)->toBe(200.0); // Restored to 200
    });
});

describe('3. Sale Module - Inventory Updates via API', function () {

    it('reduces inventory when sale is created via API', function () {
        // Create item via API to ensure inventory is initialized
        $itemResponse = $this->postJson(route('setups.items.store'), [
            'short_name' => 'Sale Test Item',
            'description' => 'Item for sale test',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
            'tax_code_id' => $this->taxCode->id,
            'base_cost' => 80.00,
            'base_sell' => 100.00,
            'starting_quantity' => 100,
            'is_active' => true,
        ]);

        $itemResponse->assertCreated();
        $itemId = $itemResponse->json('data.id');

        $response = $this->postJson(route('customers.sales.store'), [
            'date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse1->id,
            'currency_id' => $this->currency->id,
            'currency_rate' => 1.0,
            'prefix' => 'INV',
            'sub_total' => 3000,
            'sub_total_usd' => 3000,
            'discount_amount' => 0,
            'discount_amount_usd' => 0,
            'total' => 3000,
            'total_usd' => 3000,
            'total_tax_amount' => 0,
            'total_tax_amount_usd' => 0,
            'items' => [
                [
                    'item_id' => $itemId,
                    'quantity' => 30,
                    'price' => 100,
                    'price_usd' => 100,
                    'ttc_price' => 100,
                    'ttc_price_usd' => 100,
                    'cost_price' => 80,
                    'discount_percent' => 0,
                    'unit_discount_amount' => 0,
                    'unit_discount_amount_usd' => 0,
                    'discount_amount' => 0,
                    'discount_amount_usd' => 0,
                    'tax_percent' => 0,
                    'total_price' => 3000,
                    'total_price_usd' => 3000,
                    'unit_profit' => 20,
                    'total_profit' => 600,
                ]
            ]
        ]);

        $response->assertCreated();

        $quantity = ($this->getInventoryQuantity)($itemId, $this->warehouse1->id);
        expect($quantity)->toBe(70.0); // 100 - 30
    });

    it('updates inventory when sale item quantity is updated via API', function () {
        // Create item via API
        $itemResponse = $this->postJson(route('setups.items.store'), [
            'short_name' => 'Update Test Item',
            'description' => 'Item for update test',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
            'tax_code_id' => $this->taxCode->id,
            'base_cost' => 80.00,
            'base_sell' => 100.00,
            'starting_quantity' => 100,
            'is_active' => true,
        ]);

        $itemResponse->assertCreated();
        $itemId = $itemResponse->json('data.id');

        // Create sale
        $response = $this->postJson(route('customers.sales.store'), [
            'date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse1->id,
            'prefix' => 'INV',
            'currency_id' => $this->currency->id,
            'currency_rate' => 1.0,
            'sub_total' => 2000,
            'sub_total_usd' => 2000,
            'discount_amount' => 0,
            'discount_amount_usd' => 0,
            'total' => 2000,
            'total_usd' => 2000,
            'total_tax_amount' => 0,
            'total_tax_amount_usd' => 0,
            'items' => [
                [
                    'item_id' => $itemId,
                    'quantity' => 20,
                    'price' => 100,
                    'price_usd' => 100,
                    'ttc_price' => 100,
                    'ttc_price_usd' => 100,
                    'cost_price' => 80,
                    'discount_percent' => 0,
                    'unit_discount_amount' => 0,
                    'unit_discount_amount_usd' => 0,
                    'discount_amount' => 0,
                    'discount_amount_usd' => 0,
                    'tax_percent' => 0,
                    'total_price' => 2000,
                    'total_price_usd' => 2000,
                    'unit_profit' => 20,
                    'total_profit' => 400,
                ]
            ]
        ]);

        $response->assertCreated();
        $saleId = $response->json('data.id');
        $saleItemId = $response->json('data.items.0.id');

        expect(($this->getInventoryQuantity)($itemId, $this->warehouse1->id))->toBe(80.0);

        // Update sale to increase quantity to 50
        $updateResponse = $this->putJson(route('customers.sales.update', $saleId), [
            'date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse1->id,
            'currency_id' => $this->currency->id,
            'currency_rate' => 1.0,
            'sub_total' => 5000,
            'sub_total_usd' => 5000,
            'discount_amount' => 0,
            'discount_amount_usd' => 0,
            'total' => 5000,
            'total_usd' => 5000,
            'total_tax_amount' => 0,
            'total_tax_amount_usd' => 0,
            'items' => [
                [
                    'id' => $saleItemId,
                    'item_id' => $itemId,
                    'quantity' => 50,
                    'price' => 100,
                    'price_usd' => 100,
                    'ttc_price' => 100,
                    'ttc_price_usd' => 100,
                    'cost_price' => 80,
                    'discount_percent' => 0,
                    'unit_discount_amount' => 0,
                    'unit_discount_amount_usd' => 0,
                    'discount_amount' => 0,
                    'discount_amount_usd' => 0,
                    'tax_percent' => 0,
                    'total_price' => 5000,
                    'total_price_usd' => 5000,
                    'unit_profit' => 20,
                    'total_profit' => 1000,
                ]
            ]
        ]);

        $updateResponse->assertOk();

        $quantity = ($this->getInventoryQuantity)($itemId, $this->warehouse1->id);
        expect($quantity)->toBe(50.0); // 100 - 50
    });

    it('restores inventory when sale is deleted via API', function () {
        // Create item via API
        $itemResponse = $this->postJson(route('setups.items.store'), [
            'short_name' => 'Delete Test Item',
            'description' => 'Item for delete test',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
            'tax_code_id' => $this->taxCode->id,
            'base_cost' => 80.00,
            'base_sell' => 100.00,
            'starting_quantity' => 100,
            'is_active' => true,
        ]);

        $itemResponse->assertCreated();
        $itemId = $itemResponse->json('data.id');

        // Create sale
        $response = $this->postJson(route('customers.sales.store'), [
            'date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse1->id,
            'prefix' => 'INV',
            'currency_id' => $this->currency->id,
            'currency_rate' => 1.0,
            'sub_total' => 2500,
            'sub_total_usd' => 2500,
            'discount_amount' => 0,
            'discount_amount_usd' => 0,
            'total' => 2500,
            'total_usd' => 2500,
            'total_tax_amount' => 0,
            'total_tax_amount_usd' => 0,
            'items' => [
                [
                    'item_id' => $itemId,
                    'quantity' => 25,
                    'price' => 100,
                    'price_usd' => 100,
                    'ttc_price' => 100,
                    'ttc_price_usd' => 100,
                    'cost_price' => 80,
                    'discount_percent' => 0,
                    'unit_discount_amount' => 0,
                    'unit_discount_amount_usd' => 0,
                    'discount_amount' => 0,
                    'discount_amount_usd' => 0,
                    'tax_percent' => 0,
                    'total_price' => 2500,
                    'total_price_usd' => 2500,
                    'unit_profit' => 20,
                    'total_profit' => 500,
                ]
            ]
        ]);

        $response->assertCreated();
        $saleId = $response->json('data.id');

        expect(($this->getInventoryQuantity)($itemId, $this->warehouse1->id))->toBe(75.0);

        // Delete sale
        $deleteResponse = $this->deleteJson(route('customers.sales.destroy', $saleId));
        $deleteResponse->assertNoContent();

        $quantity = ($this->getInventoryQuantity)($itemId, $this->warehouse1->id);
        expect($quantity)->toBe(100.0); // Fully restored
    });
});

describe('3a. Sale Return Module - Inventory Updates via API', function () {

    it('inventory does not change when customer return is created but increases when marked as received', function () {
        // Create item via API
        $itemResponse = $this->postJson(route('setups.items.store'), [
            'short_name' => 'Sale Return Test Item',
            'description' => 'Item for sale return test',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
            'tax_code_id' => $this->taxCode->id,
            'base_cost' => 80.00,
            'base_sell' => 100.00,
            'starting_quantity' => 100,
            'is_active' => true,
        ]);

        $itemResponse->assertCreated();
        $itemId = $itemResponse->json('data.id');
        $itemCode = $itemResponse->json('data.code');

        // Create sale first
        $saleResponse = $this->postJson(route('customers.sales.store'), [
            'date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse1->id,
            'prefix' => 'INV',
            'currency_id' => $this->currency->id,
            'currency_rate' => 1.0,
            'sub_total' => 5000,
            'sub_total_usd' => 5000,
            'discount_amount' => 0,
            'discount_amount_usd' => 0,
            'total' => 5000,
            'total_usd' => 5000,
            'total_tax_amount' => 0,
            'total_tax_amount_usd' => 0,
            'items' => [
                [
                    'item_id' => $itemId,
                    'quantity' => 50,
                    'price' => 100,
                    'price_usd' => 100,
                    'ttc_price' => 100,
                    'ttc_price_usd' => 100,
                    'cost_price' => 80,
                    'discount_percent' => 0,
                    'unit_discount_amount' => 0,
                    'unit_discount_amount_usd' => 0,
                    'discount_amount' => 0,
                    'discount_amount_usd' => 0,
                    'tax_percent' => 0,
                    'total_price' => 5000,
                    'total_price_usd' => 5000,
                    'unit_profit' => 20,
                    'total_profit' => 1000,
                ]
            ]
        ]);

        $saleResponse->assertCreated();
        expect(($this->getInventoryQuantity)($itemId, $this->warehouse1->id))->toBe(50.0); // 100 - 50

        // Create sale return via API (not yet received)
        $response = $this->postJson(route('customers.returns.store'), [
            'date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse1->id,
            'prefix' => 'RTN',
            'currency_id' => $this->currency->id,
            'currency_rate' => 1.0,
            'sub_total' => 2000,
            'sub_total_usd' => 2000,
            'discount_amount' => 0,
            'discount_amount_usd' => 0,
            'total' => 2000,
            'total_usd' => 2000,
            'total_tax_amount' => 0,
            'total_tax_amount_usd' => 0,
            'items' => [
                [
                    'item_id' => $itemId,
                    'quantity' => 20,
                    'item_code' => $itemCode,
                    'price' => 100,
                    'price_usd' => 100,
                    'ttc_price' => 100,
                    'ttc_price_usd' => 100,
                    'cost_price' => 80,
                    'discount_percent' => 0,
                    'unit_discount_amount' => 0,
                    'unit_discount_amount_usd' => 0,
                    'discount_amount' => 0,
                    'discount_amount_usd' => 0,
                    'tax_percent' => 0,
                    'total_price' => 2000,
                    'total_price_usd' => 2000,
                    'unit_profit' => 20,
                    'total_profit' => 400,
                ]
            ]
        ]);

        $response->assertCreated();
        $returnId = $response->json('data.id');

        // Verify inventory doesn't change when return is created
        $quantity = ($this->getInventoryQuantity)($itemId, $this->warehouse1->id);
        expect($quantity)->toBe(50.0); // Still 50 (not yet received)

        // Mark return as received
        $markReceivedResponse = $this->patchJson(route('customers.returns.markReceived', $returnId), [
            'note' => 'Items received back from customer'
        ]);

        $markReceivedResponse->assertOk();

        // Verify inventory increases after marking as received
        $quantity = ($this->getInventoryQuantity)($itemId, $this->warehouse1->id);
        expect($quantity)->toBe(70.0); // 50 + 20 (now received)
    });

    it('inventory does not change when updating customer return before it is received', function () {
        // Create item via API
        $itemResponse = $this->postJson(route('setups.items.store'), [
            'short_name' => 'Sale Return Update Item',
            'description' => 'Item for sale return update test',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
            'tax_code_id' => $this->taxCode->id,
            'base_cost' => 80.00,
            'base_sell' => 100.00,
            'starting_quantity' => 100,
            'is_active' => true,
        ]);

        $itemResponse->assertCreated();
        $itemId = $itemResponse->json('data.id');
        $itemCode = $itemResponse->json('data.code');

        // Create sale first
        $saleResponse = $this->postJson(route('customers.sales.store'), [
            'date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse1->id,
            'prefix' => 'INV',
            'currency_id' => $this->currency->id,
            'currency_rate' => 1.0,
            'sub_total' => 6000,
            'sub_total_usd' => 6000,
            'discount_amount' => 0,
            'discount_amount_usd' => 0,
            'total' => 6000,
            'total_usd' => 6000,
            'total_tax_amount' => 0,
            'total_tax_amount_usd' => 0,
            'items' => [
                [
                    'item_id' => $itemId,
                    'quantity' => 60,
                    'price' => 100,
                    'price_usd' => 100,
                    'ttc_price' => 100,
                    'ttc_price_usd' => 100,
                    'cost_price' => 80,
                    'discount_percent' => 0,
                    'unit_discount_amount' => 0,
                    'unit_discount_amount_usd' => 0,
                    'discount_amount' => 0,
                    'discount_amount_usd' => 0,
                    'tax_percent' => 0,
                    'total_price' => 6000,
                    'total_price_usd' => 6000,
                    'unit_profit' => 20,
                    'total_profit' => 1200,
                ]
            ]
        ]);

        $saleResponse->assertCreated();
        expect(($this->getInventoryQuantity)($itemId, $this->warehouse1->id))->toBe(40.0); // 100 - 60

        // Create sale return (not yet received)
        $response = $this->postJson(route('customers.returns.store'), [
            'date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse1->id,
            'prefix' => 'RTN',
            'currency_id' => $this->currency->id,
            'currency_rate' => 1.0,
            'sub_total' => 1000,
            'sub_total_usd' => 1000,
            'discount_amount' => 0,
            'discount_amount_usd' => 0,
            'total' => 1000,
            'total_usd' => 1000,
            'total_tax_amount' => 0,
            'total_tax_amount_usd' => 0,
            'items' => [
                [
                    'item_id' => $itemId,
                    'quantity' => 10,
                    'price' => 100,
                    'item_code' => $itemCode,
                    'price_usd' => 100,
                    'ttc_price' => 100,
                    'ttc_price_usd' => 100,
                    'cost_price' => 80,
                    'discount_percent' => 0,
                    'unit_discount_amount' => 0,
                    'unit_discount_amount_usd' => 0,
                    'discount_amount' => 0,
                    'discount_amount_usd' => 0,
                    'tax_percent' => 0,
                    'total_price' => 1000,
                    'total_price_usd' => 1000,
                    'unit_profit' => 20,
                    'total_profit' => 200,
                ]
            ]
        ]);

        $response->assertCreated();
        $returnId = $response->json('data.id');
        $returnItemId = $response->json('data.items.0.id');

        // Verify inventory doesn't change after creating return
        expect(($this->getInventoryQuantity)($itemId, $this->warehouse1->id))->toBe(40.0); // Still 40 (not yet received)

        // Update sale return to increase return quantity to 30 (still not received)
        $updateResponse = $this->putJson(route('customers.returns.update', $returnId), [
            'date' => now()->format('Y-m-d'),
            'prefix' => 'RTN',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse1->id,
            'currency_id' => $this->currency->id,
            'currency_rate' => 1.0,
            'sub_total' => 3000,
            'sub_total_usd' => 3000,
            'discount_amount' => 0,
            'discount_amount_usd' => 0,
            'total' => 3000,
            'total_usd' => 3000,
            'total_tax_amount' => 0,
            'total_tax_amount_usd' => 0,
            'items' => [
                [
                    'id' => $returnItemId,
                    'item_id' => $itemId,
                    'item_code' => $itemCode,
                    'quantity' => 30,
                    'price' => 100,
                    'price_usd' => 100,
                    'ttc_price' => 100,
                    'ttc_price_usd' => 100,
                    'cost_price' => 80,
                    'discount_percent' => 0,
                    'unit_discount_amount' => 0,
                    'unit_discount_amount_usd' => 0,
                    'discount_amount' => 0,
                    'discount_amount_usd' => 0,
                    'tax_percent' => 0,
                    'total_price' => 3000,
                    'total_price_usd' => 3000,
                    'unit_profit' => 20,
                    'total_profit' => 600,
                ]
            ]
        ]);

        $updateResponse->assertOk();

        // Verify inventory still doesn't change after updating (not yet received)
        $quantity = ($this->getInventoryQuantity)($itemId, $this->warehouse1->id);
        expect($quantity)->toBe(40.0); // Still 40 (not yet received)

        // Now mark the return as received
        $markReceivedResponse = $this->patchJson(route('customers.returns.markReceived', $returnId), [
            'note' => 'Items received back from customer'
        ]);

        $markReceivedResponse->assertOk();

        // Verify inventory increases after marking as received
        $quantity = ($this->getInventoryQuantity)($itemId, $this->warehouse1->id);
        expect($quantity)->toBe(70.0); // 40 + 30 (now received)
    });

    it('inventory does not change when deleting non-received return but decreases when deleting received return', function () {
        // Create item via API
        $itemResponse = $this->postJson(route('setups.items.store'), [
            'short_name' => 'Sale Return Delete Item',
            'description' => 'Item for sale return delete test',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
            'tax_code_id' => $this->taxCode->id,
            'base_cost' => 80.00,
            'base_sell' => 100.00,
            'starting_quantity' => 100,
            'is_active' => true,
        ]);

        $itemResponse->assertCreated();
        $itemId = $itemResponse->json('data.id');
        $itemCode = $itemResponse->json('data.code');

        // Create sale first
        $saleResponse = $this->postJson(route('customers.sales.store'), [
            'date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse1->id,
            'prefix' => 'INV',
            'currency_id' => $this->currency->id,
            'currency_rate' => 1.0,
            'sub_total' => 5000,
            'sub_total_usd' => 5000,
            'discount_amount' => 0,
            'discount_amount_usd' => 0,
            'total' => 5000,
            'total_usd' => 5000,
            'total_tax_amount' => 0,
            'total_tax_amount_usd' => 0,
            'items' => [
                [
                    'item_id' => $itemId,
                    'quantity' => 50,
                    'price' => 100,
                    'price_usd' => 100,
                    'ttc_price' => 100,
                    'ttc_price_usd' => 100,
                    'cost_price' => 80,
                    'discount_percent' => 0,
                    'unit_discount_amount' => 0,
                    'unit_discount_amount_usd' => 0,
                    'discount_amount' => 0,
                    'discount_amount_usd' => 0,
                    'tax_percent' => 0,
                    'total_price' => 5000,
                    'total_price_usd' => 5000,
                    'unit_profit' => 20,
                    'total_profit' => 1000,
                ]
            ]
        ]);

        $saleResponse->assertCreated();
        expect(($this->getInventoryQuantity)($itemId, $this->warehouse1->id))->toBe(50.0); // 100 - 50

        // TEST 1: Create and delete a non-received return (should have no effect on inventory)
        $return1Response = $this->postJson(route('customers.returns.store'), [
            'date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse1->id,
            'prefix' => 'RTN',
            'currency_id' => $this->currency->id,
            'currency_rate' => 1.0,
            'sub_total' => 1000,
            'sub_total_usd' => 1000,
            'discount_amount' => 0,
            'discount_amount_usd' => 0,
            'total' => 1000,
            'total_usd' => 1000,
            'total_tax_amount' => 0,
            'total_tax_amount_usd' => 0,
            'items' => [
                [
                    'item_id' => $itemId,
                    'quantity' => 10,
                    'item_code' => $itemCode,
                    'price' => 100,
                    'price_usd' => 100,
                    'ttc_price' => 100,
                    'ttc_price_usd' => 100,
                    'cost_price' => 80,
                    'discount_percent' => 0,
                    'unit_discount_amount' => 0,
                    'unit_discount_amount_usd' => 0,
                    'discount_amount' => 0,
                    'discount_amount_usd' => 0,
                    'tax_percent' => 0,
                    'total_price' => 1000,
                    'total_price_usd' => 1000,
                    'unit_profit' => 20,
                    'total_profit' => 200,
                ]
            ]
        ]);

        $return1Response->assertCreated();
        $return1Id = $return1Response->json('data.id');

        // Verify inventory doesn't change after creating return
        expect(($this->getInventoryQuantity)($itemId, $this->warehouse1->id))->toBe(50.0); // Still 50 (not yet received)

        // Delete non-received return
        $delete1Response = $this->deleteJson(route('customers.returns.destroy', $return1Id));
        $delete1Response->assertNoContent();

        // Verify inventory still doesn't change after deleting non-received return
        $quantity = ($this->getInventoryQuantity)($itemId, $this->warehouse1->id);
        expect($quantity)->toBe(50.0); // Still 50 (was not received, so no inventory effect)

        // TEST 2: Create, mark as received, then delete a received return (should decrease inventory)
        $return2Response = $this->postJson(route('customers.returns.store'), [
            'date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse1->id,
            'prefix' => 'RTN',
            'currency_id' => $this->currency->id,
            'currency_rate' => 1.0,
            'sub_total' => 1500,
            'sub_total_usd' => 1500,
            'discount_amount' => 0,
            'discount_amount_usd' => 0,
            'total' => 1500,
            'total_usd' => 1500,
            'total_tax_amount' => 0,
            'total_tax_amount_usd' => 0,
            'items' => [
                [
                    'item_id' => $itemId,
                    'quantity' => 15,
                    'item_code' => $itemCode,
                    'price' => 100,
                    'price_usd' => 100,
                    'ttc_price' => 100,
                    'ttc_price_usd' => 100,
                    'cost_price' => 80,
                    'discount_percent' => 0,
                    'unit_discount_amount' => 0,
                    'unit_discount_amount_usd' => 0,
                    'discount_amount' => 0,
                    'discount_amount_usd' => 0,
                    'tax_percent' => 0,
                    'total_price' => 1500,
                    'total_price_usd' => 1500,
                    'unit_profit' => 20,
                    'total_profit' => 300,
                ]
            ]
        ]);

        $return2Response->assertCreated();
        $return2Id = $return2Response->json('data.id');

        // Verify inventory still at 50 (not yet received)
        expect(($this->getInventoryQuantity)($itemId, $this->warehouse1->id))->toBe(50.0); // Still 50

        // Mark return as received
        $markReceivedResponse = $this->patchJson(route('customers.returns.markReceived', $return2Id), [
            'note' => 'Items received back from customer'
        ]);

        $markReceivedResponse->assertOk();

        // Verify inventory increased after marking as received
        expect(($this->getInventoryQuantity)($itemId, $this->warehouse1->id))->toBe(65.0); // 50 + 15

        // Delete received return
        $delete2Response = $this->deleteJson(route('customers.returns.destroy', $return2Id));
        $delete2Response->assertNoContent();

        // Verify inventory decreased after deleting received return
        $quantity = ($this->getInventoryQuantity)($itemId, $this->warehouse1->id);
        expect($quantity)->toBe(50.0); // Back to 50 (return was received, so inventory is subtracted on delete)
    });
});

describe('4. Stock Adjust Module - Inventory Updates via API', function () {

    it('increases inventory when adjustment type is Add via API', function () {
        // Create item via API
        $itemResponse = $this->postJson(route('setups.items.store'), [
            'short_name' => 'Adjust Add Test Item',
            'description' => 'Item for adjust add test',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
            'tax_code_id' => $this->taxCode->id,
            'base_cost' => 80.00,
            'base_sell' => 100.00,
            'starting_quantity' => 100,
            'is_active' => true,
        ]);

        $itemResponse->assertCreated();
        $itemId = $itemResponse->json('data.id');

        $response = $this->postJson(route('items.adjusts.store'), [
            'date' => now()->format('Y-m-d'),
            'warehouse_id' => $this->warehouse1->id,
            'type' => 'Add',
            'note' => 'Stock adjustment test',
            'items' => [
                [
                    'item_id' => $itemId,
                    'quantity' => 50,
                    'note' => 'Adding stock',
                ]
            ]
        ]);

        $response->assertCreated();

        $quantity = ($this->getInventoryQuantity)($itemId, $this->warehouse1->id);
        expect($quantity)->toBe(150.0); // 100 + 50
    });

    it('decreases inventory when adjustment type is Subtract via API', function () {
        // Create item via API
        $itemResponse = $this->postJson(route('setups.items.store'), [
            'short_name' => 'Adjust Subtract Test Item',
            'description' => 'Item for adjust subtract test',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
            'tax_code_id' => $this->taxCode->id,
            'base_cost' => 80.00,
            'base_sell' => 100.00,
            'starting_quantity' => 100,
            'is_active' => true,
        ]);

        $itemResponse->assertCreated();
        $itemId = $itemResponse->json('data.id');

        $response = $this->postJson(route('items.adjusts.store'), [
            'date' => now()->format('Y-m-d'),
            'warehouse_id' => $this->warehouse1->id,
            'type' => 'Subtract',
            'note' => 'Stock adjustment test',
            'items' => [
                [
                    'item_id' => $itemId,
                    'quantity' => 30,
                    'note' => 'Removing stock',
                ]
            ]
        ]);

        $response->assertCreated();

        $quantity = ($this->getInventoryQuantity)($itemId, $this->warehouse1->id);
        expect($quantity)->toBe(70.0); // 100 - 30
    });
});

describe('5. Stock Transfer Module - Inventory Updates Between Warehouses via API', function () {

    it('transfers inventory from one warehouse to another via API', function () {
        // Create item via API
        $itemResponse = $this->postJson(route('setups.items.store'), [
            'short_name' => 'Transfer Test Item',
            'description' => 'Item for transfer test',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
            'tax_code_id' => $this->taxCode->id,
            'base_cost' => 80.00,
            'base_sell' => 100.00,
            'starting_quantity' => 100,
            'is_active' => true,
        ]);

        $itemResponse->assertCreated();
        $itemId = $itemResponse->json('data.id');

        $response = $this->postJson(route('items.transfers.store'), [
            'date' => now()->format('Y-m-d'),
            'from_warehouse_id' => $this->warehouse1->id,
            'to_warehouse_id' => $this->warehouse2->id,
            'note' => 'Transfer test',
            'items' => [
                [
                    'item_id' => $itemId,
                    'quantity' => 30,
                    'note' => 'Transferring stock',
                ]
            ]
        ]);

        $response->assertCreated();

        $warehouse1Qty = ($this->getInventoryQuantity)($itemId, $this->warehouse1->id);
        $warehouse2Qty = ($this->getInventoryQuantity)($itemId, $this->warehouse2->id);

        expect($warehouse1Qty)->toBe(70.0); // 100 - 30
        expect($warehouse2Qty)->toBe(30.0); // 0 + 30
    });

    it('maintains correct total quantity across warehouses after transfer via API', function () {
        // Create item via API
        $itemResponse = $this->postJson(route('setups.items.store'), [
            'short_name' => 'Transfer Total Test Item',
            'description' => 'Item for transfer total test',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
            'tax_code_id' => $this->taxCode->id,
            'base_cost' => 80.00,
            'base_sell' => 100.00,
            'starting_quantity' => 150,
            'is_active' => true,
        ]);

        $itemResponse->assertCreated();
        $itemId = $itemResponse->json('data.id');

        $response = $this->postJson(route('items.transfers.store'), [
            'date' => now()->format('Y-m-d'),
            'from_warehouse_id' => $this->warehouse1->id,
            'to_warehouse_id' => $this->warehouse2->id,
            'note' => 'Transfer test',
            'items' => [
                [
                    'item_id' => $itemId,
                    'quantity' => 60,
                    'note' => 'Transferring stock',
                ]
            ]
        ]);

        $response->assertCreated();

        $totalQty = ($this->getInventoryQuantity)($itemId, $this->warehouse1->id)
                  + ($this->getInventoryQuantity)($itemId, $this->warehouse2->id);

        expect($totalQty)->toBe(150.0); // Total should remain the same
    });
});

describe('6. Comprehensive Multi-Transaction Test via API', function () {

    it('maintains correct inventory through complex transaction sequence via API', function () {
        // 1. Create item with initial quantity
        $itemResponse = $this->postJson(route('setups.items.store'), [
            'short_name' => 'Comprehensive Test Item',
            'description' => 'Testing complex transactions',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
            'tax_code_id' => $this->taxCode->id,
            'base_cost' => 80.00,
            'base_sell' => 100.00,
            'starting_quantity' => 1000,
            'is_active' => true,
        ]);

        $itemResponse->assertCreated();
        $itemId = $itemResponse->json('data.id');

        expect(($this->getInventoryQuantity)($itemId, $this->warehouse1->id))->toBe(1000.0);

        // 2. Purchase - add 500 units
        $purchaseResponse = $this->postJson(route('suppliers.purchases.store'), [
            'date' => now()->format('Y-m-d'),
            'prefix' => 'PUR',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse1->id,
            'currency_id' => $this->currency->id,
            'currency_rate' => 1.0,
            'sub_total' => 5000,
            'sub_total_usd' => 5000,
            'discount_amount' => 0,
            'discount_amount_usd' => 0,
            'total' => 5000,
            'total_usd' => 5000,
            'final_total' => 5000,
            'final_total_usd' => 5000,
            'shipping_fee_usd' => 0,
            'customs_fee_usd' => 0,
            'other_fee_usd' => 0,
            'tax_usd' => 0,
            'items' => [
                [
                    'item_id' => $itemId,
                    'price' => 10.00,
                    'quantity' => 500,
                    'discount_percent' => 0,
                    'discount_amount' => 0,
                ]
            ]
        ]);

        $purchaseResponse->assertCreated();
        expect(($this->getInventoryQuantity)($itemId, $this->warehouse1->id))->toBe(1500.0);

        // 3. Sale - sell 300 units
        $saleResponse = $this->postJson(route('customers.sales.store'), [
            'date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'prefix' => 'INV',
            'warehouse_id' => $this->warehouse1->id,
            'currency_id' => $this->currency->id,
            'currency_rate' => 1.0,
            'sub_total' => 30000,
            'sub_total_usd' => 30000,
            'discount_amount' => 0,
            'discount_amount_usd' => 0,
            'total' => 30000,
            'total_usd' => 30000,
            'total_tax_amount' => 0,
            'total_tax_amount_usd' => 0,
            'items' => [
                [
                    'item_id' => $itemId,
                    'quantity' => 300,
                    'price' => 100,
                    'price_usd' => 100,
                    'ttc_price' => 100,
                    'ttc_price_usd' => 100,
                    'cost_price' => 80,
                    'discount_percent' => 0,
                    'unit_discount_amount' => 0,
                    'unit_discount_amount_usd' => 0,
                    'discount_amount' => 0,
                    'discount_amount_usd' => 0,
                    'tax_percent' => 0,
                    'total_price' => 30000,
                    'total_price_usd' => 30000,
                    'unit_profit' => 20,
                    'total_profit' => 6000,
                ]
            ]
        ]);

        $saleResponse->assertCreated();
        expect(($this->getInventoryQuantity)($itemId, $this->warehouse1->id))->toBe(1200.0);

        // 4. Transfer 400 units to warehouse2
        $transferResponse = $this->postJson(route('items.transfers.store'), [
            'date' => now()->format('Y-m-d'),
            'from_warehouse_id' => $this->warehouse1->id,
            'to_warehouse_id' => $this->warehouse2->id,
            'note' => 'Transfer to second warehouse',
            'items' => [
                [
                    'item_id' => $itemId,
                    'quantity' => 400,
                    'note' => 'Bulk transfer',
                ]
            ]
        ]);

        $transferResponse->assertCreated();
        expect(($this->getInventoryQuantity)($itemId, $this->warehouse1->id))->toBe(800.0);
        expect(($this->getInventoryQuantity)($itemId, $this->warehouse2->id))->toBe(400.0);

        // 5. Adjustment - add 100 units to warehouse1
        $adjustResponse = $this->postJson(route('items.adjusts.store'), [
            'date' => now()->format('Y-m-d'),
            'warehouse_id' => $this->warehouse1->id,
            'type' => 'Add',
            'note' => 'Stock count adjustment',
            'items' => [
                [
                    'item_id' => $itemId,
                    'quantity' => 100,
                    'note' => 'Found extra stock',
                ]
            ]
        ]);

        $adjustResponse->assertCreated();
        expect(($this->getInventoryQuantity)($itemId, $this->warehouse1->id))->toBe(900.0);

        // 6. Another sale from warehouse2 - 150 units
        $sale2Response = $this->postJson(route('customers.sales.store'), [
            'date' => now()->format('Y-m-d'),
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse2->id,
            'currency_id' => $this->currency->id,
            'currency_rate' => 1.0,
            'prefix' => 'INV',
            'sub_total' => 15000,
            'sub_total_usd' => 15000,
            'discount_amount' => 0,
            'discount_amount_usd' => 0,
            'total' => 15000,
            'total_usd' => 15000,
            'total_tax_amount' => 0,
            'total_tax_amount_usd' => 0,
            'items' => [
                [
                    'item_id' => $itemId,
                    'quantity' => 150,
                    'price' => 100,
                    'price_usd' => 100,
                    'ttc_price' => 100,
                    'ttc_price_usd' => 100,
                    'cost_price' => 80,
                    'discount_percent' => 0,
                    'unit_discount_amount' => 0,
                    'unit_discount_amount_usd' => 0,
                    'discount_amount' => 0,
                    'discount_amount_usd' => 0,
                    'tax_percent' => 0,
                    'total_price' => 15000,
                    'total_price_usd' => 15000,
                    'unit_profit' => 20,
                    'total_profit' => 3000,
                ]
            ]
        ]);

        $sale2Response->assertCreated();
        expect(($this->getInventoryQuantity)($itemId, $this->warehouse2->id))->toBe(250.0);

        // Final verification
        $totalInventory = ($this->getInventoryQuantity)($itemId, $this->warehouse1->id)
                        + ($this->getInventoryQuantity)($itemId, $this->warehouse2->id);

        // Breakdown:
        // Started: 1000
        // + Purchase: 500
        // - Sale: 300
        // + Adjustment: 100
        // - Sale2: 150
        // = 1150 total across both warehouses

        expect($totalInventory)->toBe(1150.0);
        expect(($this->getInventoryQuantity)($itemId, $this->warehouse1->id))->toBe(900.0);
        expect(($this->getInventoryQuantity)($itemId, $this->warehouse2->id))->toBe(250.0);
    });
});
