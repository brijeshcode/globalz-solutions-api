<?php

use App\Models\Inventory\ItemPriceHistory;
use App\Models\Items\Item;
use Tests\Feature\Calculations\ItemPrice\Concerns\HasItemPriceSetup;

uses(HasItemPriceSetup::class);
uses()->group('calculations', 'item-price');

// Tests: ItemPrice calculation when purchases are delivered.
// Focuses on starting_price/starting_quantity interactions — basic purchase-only
// scenarios (no starting inventory) are covered in Feature/Suppliers/Purchases/PurchaseItemPrice*.php.
// Module: Purchases (suppliers.purchases.*)
// If the Purchase module is removed, delete this file.

beforeEach(function () {
    $this->setUpItemPrice();
});

// ─── Weighted Average with starting inventory ──────────────────────────────────

it('includes starting_quantity and starting_price in weighted average calculation', function () {
    // Item created via API: starting 100 units @ $10
    $response = $this->postJson(route('setups.items.store'), $this->itemPayload([
        'starting_price'    => 10.00,
        'starting_quantity' => 100,
        'cost_calculation'  => Item::COST_WEIGHTED_AVERAGE,
    ]));
    $response->assertCreated();
    $itemId = $response->json('data.id');

    // Purchase: 100 units @ $20
    // WA = ((100 × $10) + (100 × $20)) / 200 = $15
    $this->createDeliveredPurchase($itemId, 100, 20.00);

    expect($this->currentPrice($itemId))->toBe(15.0);
});

it('calculates weighted average across three purchases with starting inventory', function () {
    // Starting: 100 units @ $10
    $response = $this->postJson(route('setups.items.store'), $this->itemPayload([
        'starting_price'    => 10.00,
        'starting_quantity' => 100,
        'cost_calculation'  => Item::COST_WEIGHTED_AVERAGE,
    ]));
    $response->assertCreated();
    $itemId = $response->json('data.id');

    // P1: 50 @ $15  → WA = ((100×10) + (50×15)) / 150 = $11.67
    $this->createDeliveredPurchase($itemId, 50, 15.00);
    expect(round($this->currentPrice($itemId), 2))->toBe(11.67);

    // P2: 30 @ $8   → WA = ((150×11.67) + (30×8)) / 180 = $11.06
    $this->createDeliveredPurchase($itemId, 30, 8.00);
    expect(round($this->currentPrice($itemId), 2))->toBe(11.06);
});

it('does not add a price history entry when purchased at same weighted average cost', function () {
    // Starting: 100 units @ $10
    $response = $this->postJson(route('setups.items.store'), $this->itemPayload([
        'starting_price'    => 10.00,
        'starting_quantity' => 100,
        'cost_calculation'  => Item::COST_WEIGHTED_AVERAGE,
    ]));
    $response->assertCreated();
    $itemId = $response->json('data.id');

    $countBefore = $this->priceHistoryCount($itemId);

    // Purchase 100 @ $10 → WA remains $10 (no change)
    $this->createDeliveredPurchase($itemId, 100, 10.00);

    expect($this->priceHistoryCount($itemId))->toBe($countBefore);
    expect($this->currentPrice($itemId))->toBe(10.0);
});

it('price history count increases only when weighted average actually changes', function () {
    $response = $this->postJson(route('setups.items.store'), $this->itemPayload([
        'starting_price'    => 10.00,
        'starting_quantity' => 100,
        'cost_calculation'  => Item::COST_WEIGHTED_AVERAGE,
    ]));
    $response->assertCreated();
    $itemId = $response->json('data.id');

    // Initial entry = 1
    expect($this->priceHistoryCount($itemId))->toBe(1);

    // Purchase at different price → price changes → new history
    $this->createDeliveredPurchase($itemId, 200, 15.00);
    expect($this->priceHistoryCount($itemId))->toBe(2);

    // Purchase at same WA result → no new history
    $currentWa = $this->currentPrice($itemId);
    // Force same WA by purchasing 0-effective amount at same price (edge: buy identical proportions)
    // Instead we just verify count doesn't increase on same-price purchase with existing inventory
    $this->createDeliveredPurchase($itemId, 300, $currentWa);
    expect($this->priceHistoryCount($itemId))->toBe(2);
});

it('only one price history entry is marked as current after multiple purchases', function () {
    $response = $this->postJson(route('setups.items.store'), $this->itemPayload([
        'starting_price'    => 10.00,
        'starting_quantity' => 100,
        'cost_calculation'  => Item::COST_WEIGHTED_AVERAGE,
    ]));
    $response->assertCreated();
    $itemId = $response->json('data.id');

    $this->createDeliveredPurchase($itemId, 100, 20.00);
    $this->createDeliveredPurchase($itemId, 50, 8.00);

    expect($this->currentHistoryCount($itemId))->toBe(1);
});

// ─── Last Cost with starting inventory ────────────────────────────────────────

it('replaces starting_price with last cost when first purchase is delivered', function () {
    // Item created via API with starting_price — tests that purchase overrides the initial entry
    $response = $this->postJson(route('setups.items.store'), $this->itemPayload([
        'starting_price'    => 10.00,
        'starting_quantity' => 100,
        'cost_calculation'  => Item::COST_LAST_COST,
    ]));
    $response->assertCreated();
    $itemId = $response->json('data.id');

    // After purchase at $15, last cost = $15 (purchase supersedes starting_price)
    $this->createDeliveredPurchase($itemId, 50, 15.00);

    expect($this->currentPrice($itemId))->toBe(15.0);
    expect($this->currentHistoryCount($itemId))->toBe(1);
});
