<?php

use App\Models\Inventory\ItemPrice;
use App\Models\Inventory\ItemPriceHistory;
use App\Models\Items\Item;
use Tests\Feature\Suppliers\Purchases\Concerns\HasPurchaseSetup;

uses(HasPurchaseSetup::class);

uses()->group('api', 'suppliers', 'purchases');

beforeEach(function () {
    $this->setUpPurchases();
});

it('sets item price from first weighted average purchase', function () {
    $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 10.00, 'quantity' => 10]],
    ]);

    $price = ItemPrice::where('item_id', $this->item1->id)->first();
    expect($price)->not()->toBeNull()
        ->and((float) $price->price_usd)->toBe(10.0);
});

it('calculates weighted average from two new purchases', function () {
    // P1: 10 @ $10 → price = $10, inventory = 10
    $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 10.00, 'quantity' => 10]],
    ]);

    // P2: 10 @ $20, inventory before delivery = 10
    // WA = (10*10 + 10*20) / 20 = $15
    $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 20.00, 'quantity' => 10]],
    ]);

    $price = ItemPrice::where('item_id', $this->item1->id)->first();
    expect((float) $price->price_usd)->toBe(15.0);
});

it('sets item price from first last cost purchase', function () {
    $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item2->id, 'price' => 10.00, 'quantity' => 10]],
    ]);

    $price = ItemPrice::where('item_id', $this->item2->id)->first();
    expect((float) $price->price_usd)->toBe(10.0);
});

it('replaces item price with last cost from second purchase', function () {
    $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item2->id, 'price' => 10.00, 'quantity' => 10]],
    ]);

    // P2 is delivered after P1 → last cost = $25
    $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item2->id, 'price' => 25.00, 'quantity' => 5]],
    ]);

    $price = ItemPrice::where('item_id', $this->item2->id)->first();
    expect((float) $price->price_usd)->toBe(25.0);
});

it('creates price history with correct fields for new weighted average purchase', function () {
    $purchase = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 10.00, 'quantity' => 10]],
    ]);

    $purchaseItem = $purchase->purchaseItems->first();
    $history      = ItemPriceHistory::where('item_id', $this->item1->id)->first();

    expect($history)->not()->toBeNull()
        ->and($history->is_current)->toBeTrue()
        ->and((float) $history->price_usd)->toBe(10.0)
        ->and($history->source_type)->toBe('purchase_item')
        ->and($history->source_id)->toBe($purchaseItem->id)
        ->and($history->calculation_type)->toBe(Item::COST_WEIGHTED_AVERAGE);
});

it('creates price history with correct fields for new last cost purchase', function () {
    $purchase = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item2->id, 'price' => 10.00, 'quantity' => 10]],
    ]);

    $purchaseItem = $purchase->purchaseItems->first();
    $history      = ItemPriceHistory::where('item_id', $this->item2->id)->first();

    expect($history)->not()->toBeNull()
        ->and($history->is_current)->toBeTrue()
        ->and((float) $history->price_usd)->toBe(10.0)
        ->and($history->source_type)->toBe('purchase_item')
        ->and($history->source_id)->toBe($purchaseItem->id)
        ->and($history->calculation_type)->toBe(Item::COST_LAST_COST);
});

it('only marks the latest history entry as current after two weighted average purchases', function () {
    $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 10.00, 'quantity' => 10]],
    ]);

    $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 20.00, 'quantity' => 10]],
    ]);

    $currentCount = ItemPriceHistory::where('item_id', $this->item1->id)->where('is_current', true)->count();
    expect($currentCount)->toBe(1);

    $current = ItemPriceHistory::where('item_id', $this->item1->id)->where('is_current', true)->first();
    expect((float) $current->price_usd)->toBe(15.0);
});
