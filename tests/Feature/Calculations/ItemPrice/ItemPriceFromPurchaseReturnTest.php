<?php

use App\Models\Inventory\ItemPriceHistory;
use App\Models\Items\Item;
use Tests\Feature\Calculations\ItemPrice\Concerns\HasItemPriceSetup;

uses(HasItemPriceSetup::class);
uses()->group('calculations', 'item-price');

// Tests: ItemPrice recalculation when purchase returns are created or deleted.
// This module has no coverage elsewhere — all scenarios here are unique.
// Module: Purchase Returns (suppliers.purchase-returns.*)
// If the Purchase Return module is removed, delete this file.

beforeEach(function () {
    $this->setUpItemPrice();
});

// ─── Weighted Average ──────────────────────────────────────────────────────────

it('price stays the same when returning at the same weighted average cost', function () {
    $item = $this->makeItem(Item::COST_WEIGHTED_AVERAGE);

    // Purchase: 200 @ $10 → price = $10
    $this->createDeliveredPurchase($item->id, 200, 10.00);
    expect($this->currentPrice($item->id))->toBe(10.0);

    // Return 50 @ $10: value removed = 50×$10 = $500
    // Remaining: 150 units, value = $2000 - $500 = $1500 → avg = $10 (no change)
    $this->createPurchaseReturn($item->id, 50, 10.00);

    expect($this->currentPrice($item->id))->toBe(10.0);
});

it('recalculates weighted average price after returning items at a higher price', function () {
    $item = $this->makeItem(Item::COST_WEIGHTED_AVERAGE);

    // P1: 100 @ $8, P2: 100 @ $12 → WA = $10, inventory = 200
    $this->createDeliveredPurchase($item->id, 100, 8.00);
    $this->createDeliveredPurchase($item->id, 100, 12.00);
    expect($this->currentPrice($item->id))->toBe(10.0);

    // Return 50 @ $12 (returning higher-priced stock)
    // Current value: 200 × $10 = $2000
    // Removed value: 50 × $12 = $600
    // Remaining: 150 units, value = $1400 → avg = $9.33
    $this->createPurchaseReturn($item->id, 50, 12.00);

    expect(round($this->currentPrice($item->id), 2))->toBe(9.33);
});

it('recalculates weighted average price after returning items at a lower price', function () {
    $item = $this->makeItem(Item::COST_WEIGHTED_AVERAGE);

    // Purchase: 100 @ $20 → price = $20, inventory = 100
    $this->createDeliveredPurchase($item->id, 100, 20.00);

    // Return 40 @ $10 (returning at cost lower than WA)
    // Current value: 100 × $20 = $2000
    // Removed value: 40 × $10 = $400
    // Remaining: 60 units, value = $1600 → avg = $26.67
    $this->createPurchaseReturn($item->id, 40, 10.00);

    expect(round($this->currentPrice($item->id), 2))->toBe(26.67);
});

it('creates a price history entry when weighted average changes after return', function () {
    $item = $this->makeItem(Item::COST_WEIGHTED_AVERAGE);

    $this->createDeliveredPurchase($item->id, 100, 8.00);
    $this->createDeliveredPurchase($item->id, 100, 12.00);
    $historyBefore = $this->priceHistoryCount($item->id);

    // Return at different price → price changes → new history
    $this->createPurchaseReturn($item->id, 50, 12.00);

    expect($this->priceHistoryCount($item->id))->toBe($historyBefore + 1);
});

it('does not create a price history entry when weighted average is unchanged after return', function () {
    $item = $this->makeItem(Item::COST_WEIGHTED_AVERAGE);

    $this->createDeliveredPurchase($item->id, 200, 10.00);
    $historyBefore = $this->priceHistoryCount($item->id);

    // Return at exact same price → no WA change → no new history
    $this->createPurchaseReturn($item->id, 50, 10.00);

    expect($this->priceHistoryCount($item->id))->toBe($historyBefore);
});

it('price history source_type is purchase_return for return-triggered entries', function () {
    $item = $this->makeItem(Item::COST_WEIGHTED_AVERAGE);

    $this->createDeliveredPurchase($item->id, 100, 10.00);

    $purchaseReturn = $this->createPurchaseReturn($item->id, 30, 5.00);

    $returnHistory = ItemPriceHistory::where('item_id', $item->id)
        ->where('source_type', 'purchase_return')
        ->first();

    expect($returnHistory)->not()->toBeNull()
        ->and($returnHistory->source_id)->toBe($purchaseReturn->id);
});

// ─── Purchase Return delete ────────────────────────────────────────────────────

it('restores weighted average price when purchase return is deleted', function () {
    $item = $this->makeItem(Item::COST_WEIGHTED_AVERAGE);

    // Purchase: 200 @ $10 → price = $10
    $this->createDeliveredPurchase($item->id, 200, 10.00);

    // Return 50 @ $12 → price changes to 9.33
    $purchaseReturn = $this->createPurchaseReturn($item->id, 50, 12.00);
    expect(round($this->currentPrice($item->id), 2))->not()->toBe(10.0);

    // Delete return → price should revert to before-return price
    $this->deleteJson(route('suppliers.purchase-returns.destroy', $purchaseReturn))->assertNoContent();

    expect($this->currentPrice($item->id))->toBe(10.0);
});

// ─── Last Cost ─────────────────────────────────────────────────────────────────

it('does not change last cost price when a purchase return is created', function () {
    $item = $this->makeItem(Item::COST_LAST_COST);

    // Purchase: 100 @ $15 → last cost = $15
    $this->createDeliveredPurchase($item->id, 100, 15.00);
    expect($this->currentPrice($item->id))->toBe(15.0);

    // Return at a different price — last cost items don't recalculate on return
    $this->createPurchaseReturn($item->id, 20, 10.00);

    expect($this->currentPrice($item->id))->toBe(15.0);
});
