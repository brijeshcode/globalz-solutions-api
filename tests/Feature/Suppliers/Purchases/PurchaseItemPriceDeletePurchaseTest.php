<?php

use App\Models\Inventory\ItemPrice;
use App\Models\Inventory\ItemPriceHistory;
use App\Services\Suppliers\PurchaseService;
use Tests\Feature\Suppliers\Purchases\Concerns\HasPurchaseSetup;

uses(HasPurchaseSetup::class);

uses()->group('api', 'suppliers', 'purchases');

beforeEach(function () {
    $this->setUpPurchases();
});

// ─── Weighted Average ──────────────────────────────────────────────────────────

it('marks price history as removed when weighted average purchase is deleted', function () {
    $purchase     = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 10.00, 'quantity' => 10]],
    ]);
    $purchaseItem = $purchase->purchaseItems->first();

    app(PurchaseService::class)->deletePurchase($purchase);

    $markedRemoved = ItemPriceHistory::where('item_id', $this->item1->id)
        ->where('source_type', 'purchase_item')
        ->where('source_id', $purchaseItem->id)
        ->where('note', 'Removed by user — no longer valid')
        ->exists();

    expect($markedRemoved)->toBeTrue();
});

it('restores weighted average price to zero when the only purchase is deleted', function () {
    $purchase = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 10.00, 'quantity' => 10]],
    ]);

    $price = ItemPrice::where('item_id', $this->item1->id)->first();
    expect((float) $price->price_usd)->toBe(10.0);

    app(PurchaseService::class)->deletePurchase($purchase);

    $price->refresh();
    expect((float) $price->price_usd)->toBe(0.0);
});

it('reverts weighted average price to previous history when newer purchase is deleted', function () {
    // P1: 10 @ $10 → price = $10
    $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 10.00, 'quantity' => 10]],
    ]);

    // P2: 10 @ $20 → WA = $15
    $purchase2 = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 20.00, 'quantity' => 10]],
    ]);

    $price = ItemPrice::where('item_id', $this->item1->id)->first();
    expect((float) $price->price_usd)->toBe(15.0);

    // Delete P2 → history entry for P2 marked removed → price reverts to P1 history ($10)
    app(PurchaseService::class)->deletePurchase($purchase2);

    $price->refresh();
    expect((float) $price->price_usd)->toBe(10.0);
});

it('keeps weighted average price from newer purchase when older purchase is deleted', function () {
    // P1: 10 @ $10 → price = $10
    $purchase1 = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 10.00, 'quantity' => 10]],
    ]);

    // P2: 10 @ $20 → WA = $15
    $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 20.00, 'quantity' => 10]],
    ]);

    // Delete P1 → P1 history entry marked removed → restores from latest non-removed (P2's $15 entry)
    app(PurchaseService::class)->deletePurchase($purchase1);

    $price = ItemPrice::where('item_id', $this->item1->id)->first();
    expect((float) $price->price_usd)->toBe(15.0);
});

// ─── Last Cost ─────────────────────────────────────────────────────────────────

it('restores last cost price to zero when the only purchase is deleted', function () {
    $purchase = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item2->id, 'price' => 10.00, 'quantity' => 10]],
    ]);

    $price = ItemPrice::where('item_id', $this->item2->id)->first();
    expect((float) $price->price_usd)->toBe(10.0);

    app(PurchaseService::class)->deletePurchase($purchase);

    $price->refresh();
    expect((float) $price->price_usd)->toBe(0.0);
});

it('reverts last cost price to older purchase cost when newer purchase is deleted', function () {
    // P1: 10 @ $10 → last cost = $10
    $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item2->id, 'price' => 10.00, 'quantity' => 10]],
    ]);

    // P2: 5 @ $25 → last cost = $25
    $purchase2 = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item2->id, 'price' => 25.00, 'quantity' => 5]],
    ]);

    $price = ItemPrice::where('item_id', $this->item2->id)->first();
    expect((float) $price->price_usd)->toBe(25.0);

    // Delete P2 → P2 history marked removed → price reverts to P1 history ($10)
    app(PurchaseService::class)->deletePurchase($purchase2);

    $price->refresh();
    expect((float) $price->price_usd)->toBe(10.0);
});

it('keeps last cost price from newer purchase when older purchase is deleted', function () {
    // P1: 10 @ $10 (older)
    $purchase1 = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item2->id, 'price' => 10.00, 'quantity' => 10]],
    ]);

    // P2: 5 @ $25 (newer) → last cost = $25
    $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item2->id, 'price' => 25.00, 'quantity' => 5]],
    ]);

    // Delete P1 → P1 history marked removed → restores from P2's history ($25)
    app(PurchaseService::class)->deletePurchase($purchase1);

    $price = ItemPrice::where('item_id', $this->item2->id)->first();
    expect((float) $price->price_usd)->toBe(25.0);
});
