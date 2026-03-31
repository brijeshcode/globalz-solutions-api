<?php

use App\Models\Inventory\Inventory;
use App\Models\Inventory\ItemPrice;
use App\Models\Inventory\ItemPriceHistory;
use Tests\Feature\Suppliers\PurchaseReturns\Concerns\HasPurchaseReturnSetup;

uses(HasPurchaseReturnSetup::class);

uses()->group('api', 'suppliers', 'purchase-returns');

beforeEach(function () {
    $this->setUpPurchaseReturns();
});

it('reduces weighted average price when items are returned at higher cost', function () {
    $this->setupInitialInventory($this->item1->id, 150, 10.67);

    $this->postJson(route('suppliers.purchase-returns.store'), $this->purchaseReturnPayload([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 12.00, 'quantity' => 30]],
    ]))->assertCreated();

    // (150 * 10.67 - 30 * 12.00) / (150 - 30) = 10.34
    $itemPrice = ItemPrice::where('item_id', $this->item1->id)->first();
    expect(round((float) $itemPrice->price_usd, 2))->toBe(10.34);

    $inventory = Inventory::where('warehouse_id', $this->warehouse->id)->where('item_id', $this->item1->id)->first();
    expect((float) $inventory->quantity)->toBe(120.0);

    $priceHistory = ItemPriceHistory::where('item_id', $this->item1->id)->where('source_type', 'purchase_return')->first();
    expect($priceHistory)->not()->toBeNull()
        ->and($priceHistory->latest_price)->toBe('10.6700');
});

it('increases weighted average price when items are returned at lower cost', function () {
    $this->setupInitialInventory($this->item1->id, 100, 15.00);

    $this->postJson(route('suppliers.purchase-returns.store'), $this->purchaseReturnPayload([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 10.00, 'quantity' => 20]],
    ]))->assertCreated();

    // (100 * 15 - 20 * 10) / (100 - 20) = 16.25
    $itemPrice = ItemPrice::where('item_id', $this->item1->id)->first();
    expect((float) $itemPrice->price_usd)->toBe(16.25);
});

it('does not affect price for COST_LAST_COST items', function () {
    $this->setupInitialInventory($this->item2->id, 100, 50.00);

    $this->postJson(route('suppliers.purchase-returns.store'), $this->purchaseReturnPayload([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item2->id, 'price' => 55.00, 'quantity' => 20]],
    ]))->assertCreated();

    $itemPrice = ItemPrice::where('item_id', $this->item2->id)->first();
    expect((float) $itemPrice->price_usd)->toBe(50.00);

    $inventory = Inventory::where('warehouse_id', $this->warehouse->id)->where('item_id', $this->item2->id)->first();
    expect((float) $inventory->quantity)->toBe(80.0);
});

it('handles multiple return updates correctly', function () {
    $this->setupInitialInventory($this->item1->id, 200, 12.00);

    $purchaseReturn = $this->createPurchaseReturnViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 15.00, 'quantity' => 30]],
    ]);

    // (200 * 12 - 30 * 15) / (200 - 30) = 11.47
    $itemPrice = ItemPrice::where('item_id', $this->item1->id)->first();
    expect(round((float) $itemPrice->price_usd, 2))->toBe(11.47);

    $returnItem = $purchaseReturn->purchaseReturnItems->first();

    $this->putJson(route('suppliers.purchase-returns.update', $purchaseReturn), [
        'currency_rate' => 1.0,
        'items'         => [
            ['id' => $returnItem->id, 'item_id' => $this->item1->id, 'price' => 15.00, 'quantity' => 50],
        ],
    ])->assertOk();

    // Update uses the current price (11.47) as basis, not the original (12.00):
    // ((170 + 30) × 11.4706 - 50 × 15) / (170 + 30 - 50) = (2294.12 - 750) / 150 = 10.29
    $itemPrice->refresh();
    expect(round((float) $itemPrice->price_usd, 2))->toBe(10.29);
});
