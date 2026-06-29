<?php

use App\Models\Inventory\ItemPrice;
use App\Models\Items\Item;
use Tests\Feature\Calculations\ItemPrice\Concerns\HasItemPriceSetup;

uses(HasItemPriceSetup::class);
uses()->group('calculations', 'item-price');

// Tests: ItemPrice restoration when a delivered purchase is deleted — specifically
// scenarios involving starting_price/starting_quantity (items created via API).
// Basic delete/restore without starting inventory is covered in
// Feature/Suppliers/Purchases/PurchaseItemPriceDeletePurchaseTest.php.
// Module: Purchases (suppliers.purchases.destroy)
// If the Purchase module is removed, delete this file.

beforeEach(function () {
    $this->setUpItemPrice();
});

// ─── Weighted Average with starting inventory ──────────────────────────────────

it('restores weighted average price to starting_price when only purchase is deleted', function () {
    // Item created via API: starting_price = $10 (creates initial history entry)
    $response = $this->postJson(route('setups.items.store'), $this->itemPayload([
        'starting_price'    => 10.00,
        'starting_quantity' => 100,
        'cost_calculation'  => Item::COST_WEIGHTED_AVERAGE,
    ]));
    $response->assertCreated();
    $itemId = $response->json('data.id');

    // Purchase: 100 @ $20 → WA = $15
    $purchase = $this->createDeliveredPurchase($itemId, 100, 20.00);
    expect($this->currentPrice($itemId))->toBe(15.0);

    // Delete purchase → should restore to last valid history entry (initial $10)
    $this->deleteJson(route('suppliers.purchases.destroy', $purchase))->assertNoContent();

    expect($this->currentPrice($itemId))->toBe(10.0);
});

it('restores weighted average price to previous purchase when newest purchase is deleted and item has starting_price', function () {
    $response = $this->postJson(route('setups.items.store'), $this->itemPayload([
        'starting_price'    => 10.00,
        'starting_quantity' => 100,
        'cost_calculation'  => Item::COST_WEIGHTED_AVERAGE,
    ]));
    $response->assertCreated();
    $itemId = $response->json('data.id');

    // P1: 100 @ $20 → WA = ((100×10) + (100×20)) / 200 = $15
    $this->createDeliveredPurchase($itemId, 100, 20.00);
    expect($this->currentPrice($itemId))->toBe(15.0);

    // P2: 100 @ $10 → WA = ((200×15) + (100×10)) / 300 = $13.33
    $purchase2 = $this->createDeliveredPurchase($itemId, 100, 10.00);
    expect(round($this->currentPrice($itemId), 2))->toBe(13.33);

    // Delete P2 → restore to P1's history entry ($15)
    $this->deleteJson(route('suppliers.purchases.destroy', $purchase2))->assertNoContent();

    expect($this->currentPrice($itemId))->toBe(15.0);
});

// ─── Last Cost with starting inventory ────────────────────────────────────────

it('restores last cost price to starting_price when only purchase is deleted', function () {
    $response = $this->postJson(route('setups.items.store'), $this->itemPayload([
        'starting_price'    => 10.00,
        'starting_quantity' => 50,
        'cost_calculation'  => Item::COST_LAST_COST,
    ]));
    $response->assertCreated();
    $itemId = $response->json('data.id');

    // Purchase: 20 @ $25 → last cost = $25
    $purchase = $this->createDeliveredPurchase($itemId, 20, 25.00);
    expect($this->currentPrice($itemId))->toBe(25.0);

    // Delete purchase → restores to initial history entry ($10)
    $this->deleteJson(route('suppliers.purchases.destroy', $purchase))->assertNoContent();

    $itemPrice = ItemPrice::where('item_id', $itemId)->first();
    expect((float) $itemPrice->price_usd)->toBe(10.0);
});

it('always has exactly one current history entry after purchase deletion', function () {
    $response = $this->postJson(route('setups.items.store'), $this->itemPayload([
        'starting_price'    => 10.00,
        'starting_quantity' => 100,
        'cost_calculation'  => Item::COST_WEIGHTED_AVERAGE,
    ]));
    $response->assertCreated();
    $itemId = $response->json('data.id');

    $this->createDeliveredPurchase($itemId, 50, 20.00);
    $purchase2 = $this->createDeliveredPurchase($itemId, 50, 15.00);

    $this->deleteJson(route('suppliers.purchases.destroy', $purchase2))->assertNoContent();

    expect($this->currentHistoryCount($itemId))->toBe(1);
});
