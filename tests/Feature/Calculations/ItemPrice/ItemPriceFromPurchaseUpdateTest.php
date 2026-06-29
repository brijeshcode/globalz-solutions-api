<?php

use App\Models\Items\Item;
use Tests\Feature\Calculations\ItemPrice\Concerns\HasItemPriceSetup;

uses(HasItemPriceSetup::class);
uses()->group('calculations', 'item-price');

// Tests: ItemPrice recalculation when a delivered purchase is updated.
// Covers: cost change vs quantity-only change, starting inventory scenarios.
// Module: Purchases (suppliers.purchases.update)
// If the Purchase module is removed, delete this file.

beforeEach(function () {
    $this->setUpItemPrice();
});

// ─── Weighted Average ──────────────────────────────────────────────────────────

it('recalculates weighted average and creates new history entry when purchase unit price changes', function () {
    // Starting: 100 @ $10
    $response = $this->postJson(route('setups.items.store'), $this->itemPayload([
        'starting_price'    => 10.00,
        'starting_quantity' => 100,
        'cost_calculation'  => Item::COST_WEIGHTED_AVERAGE,
    ]));
    $response->assertCreated();
    $itemId = $response->json('data.id');

    // Purchase: 100 @ $12 → WA = ((100×10) + (100×12)) / 200 = $11
    $purchase     = $this->createDeliveredPurchase($itemId, 100, 12.00);
    $purchaseItem = $purchase->purchaseItems->first();
    $historyBefore = $this->priceHistoryCount($itemId);

    expect($this->currentPrice($itemId))->toBe(11.0);

    // Update price to $16 → WA = ((100×10) + (100×16)) / 200 = $13
    $this->putJson(route('suppliers.purchases.update', $purchase), $this->purchasePayload([
        'items' => [['id' => $purchaseItem->id, 'item_id' => $itemId, 'price' => 16.00, 'quantity' => 100]],
    ]))->assertOk();

    expect($this->currentPrice($itemId))->toBe(13.0)
        ->and($this->priceHistoryCount($itemId))->toBe($historyBefore + 1);
});

it('recalculates weighted average correctly when purchase quantity increases', function () {
    // Starting: 100 @ $10
    $response = $this->postJson(route('setups.items.store'), $this->itemPayload([
        'starting_price'    => 10.00,
        'starting_quantity' => 100,
        'cost_calculation'  => Item::COST_WEIGHTED_AVERAGE,
    ]));
    $response->assertCreated();
    $itemId = $response->json('data.id');

    // Purchase: 100 @ $12 → WA = $11, inventory = 200
    $purchase     = $this->createDeliveredPurchase($itemId, 100, 12.00);
    $purchaseItem = $purchase->purchaseItems->first();

    // Update qty to 200 (price same $12)
    // Inventory changes from 200 → 300, but cost_per_item_usd stays $12
    // Because cost_per_item_usd didn't change → recalculateCurrentPrice (no new history)
    $historyBefore = $this->priceHistoryCount($itemId);

    $this->putJson(route('suppliers.purchases.update', $purchase), $this->purchasePayload([
        'items' => [['id' => $purchaseItem->id, 'item_id' => $itemId, 'price' => 12.00, 'quantity' => 200]],
    ]))->assertOk();

    // WA from scratch: (100×10 + 200×12) / 300 = $11.33
    expect(round($this->currentPrice($itemId), 2))->toBe(11.33)
        ->and($this->priceHistoryCount($itemId))->toBe($historyBefore); // no new history for qty-only
});

it('does not create a new price history entry when only quantity changes at the same unit price', function () {
    $item = $this->makeItem(Item::COST_WEIGHTED_AVERAGE);

    $purchase     = $this->createDeliveredPurchase($item->id, 10, 10.00);
    $purchaseItem = $purchase->purchaseItems->first();
    $historyBefore = $this->priceHistoryCount($item->id);

    // Qty 10 → 20, same price $10 → cost_per_item_usd unchanged
    $this->putJson(route('suppliers.purchases.update', $purchase), $this->purchasePayload([
        'items' => [['id' => $purchaseItem->id, 'item_id' => $item->id, 'price' => 10.00, 'quantity' => 20]],
    ]))->assertOk();

    expect($this->priceHistoryCount($item->id))->toBe($historyBefore);
    expect($this->currentPrice($item->id))->toBe(10.0);
});

// ─── Last Cost ─────────────────────────────────────────────────────────────────

it('updates last cost price and creates new history entry when purchase unit price changes', function () {
    $item = $this->makeItem(Item::COST_LAST_COST);

    $purchase     = $this->createDeliveredPurchase($item->id, 10, 10.00);
    $purchaseItem = $purchase->purchaseItems->first();
    $historyBefore = $this->priceHistoryCount($item->id);

    expect($this->currentPrice($item->id))->toBe(10.0);

    // Change price to $15 → last cost = $15
    $this->putJson(route('suppliers.purchases.update', $purchase), $this->purchasePayload([
        'items' => [['id' => $purchaseItem->id, 'item_id' => $item->id, 'price' => 15.00, 'quantity' => 10]],
    ]))->assertOk();

    expect($this->currentPrice($item->id))->toBe(15.0)
        ->and($this->priceHistoryCount($item->id))->toBe($historyBefore + 1);
});

it('does not create new history entry for last cost when only quantity changes', function () {
    $item = $this->makeItem(Item::COST_LAST_COST);

    $purchase     = $this->createDeliveredPurchase($item->id, 10, 10.00);
    $purchaseItem = $purchase->purchaseItems->first();
    $historyBefore = $this->priceHistoryCount($item->id);

    // Qty change only → cost_per_item_usd unchanged → no price history entry
    $this->putJson(route('suppliers.purchases.update', $purchase), $this->purchasePayload([
        'items' => [['id' => $purchaseItem->id, 'item_id' => $item->id, 'price' => 10.00, 'quantity' => 25]],
    ]))->assertOk();

    expect($this->priceHistoryCount($item->id))->toBe($historyBefore)
        ->and($this->currentPrice($item->id))->toBe(10.0);
});
