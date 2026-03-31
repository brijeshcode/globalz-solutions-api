<?php

use App\Models\Inventory\Inventory;
use App\Models\Inventory\ItemPrice;
use App\Models\Inventory\ItemPriceHistory;
use App\Models\Suppliers\Purchase;
use Tests\Feature\Suppliers\Purchases\Concerns\HasPurchaseSetup;

uses(HasPurchaseSetup::class);

uses()->group('api', 'suppliers', 'purchases');

beforeEach(function () {
    $this->setUpPurchases();
});

it('calculates weighted average correctly for new purchases', function () {
    // 50 items @ $2.18
    $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 2.18, 'quantity' => 50]],
    ]);

    $itemPrice = ItemPrice::where('item_id', $this->item1->id)->first();
    expect((float) $itemPrice->price_usd)->toBe(2.18);

    // 50 items @ $3.00 → weighted avg = (50*2.18 + 50*3.00) / 100 = 2.59
    $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 3.00, 'quantity' => 50]],
    ]);

    $itemPrice->refresh();
    expect((float) $itemPrice->price_usd)->toBe(2.59);

    $inventory = Inventory::where('warehouse_id', $this->warehouse->id)->where('item_id', $this->item1->id)->first();
    expect((float) $inventory->quantity)->toBe(100.0);
});

it('recalculates weighted average correctly when updating purchase price', function () {
    $purchase1 = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 2.18, 'quantity' => 50]],
    ]);
    $purchase2 = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 3.00, 'quantity' => 50]],
    ]);

    $itemPrice = ItemPrice::where('item_id', $this->item1->id)->first();
    expect((float) $itemPrice->price_usd)->toBe(2.59);

    // Update price from $3.00 → $3.10 → (50*2.18 + 50*3.10) / 100 = 2.64
    $purchaseItem = $purchase2->purchaseItems->first();
    $this->putJson(route('suppliers.purchases.update', $purchase2), [
        'final_total_usd' => 155,
        'total_usd'       => 155,
        'items'           => [
            ['id' => $purchaseItem->id, 'item_id' => $this->item1->id, 'price' => 3.10, 'quantity' => 50],
        ],
    ])->assertOk();

    $itemPrice->refresh();
    expect((float) $itemPrice->price_usd)->toBe(2.64);
});

it('maintains correct weighted average through multiple updates', function () {
    $purchase1 = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 2.18, 'quantity' => 50]],
    ]);
    $purchase2 = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 3.00, 'quantity' => 50]],
    ]);

    $itemPrice    = ItemPrice::where('item_id', $this->item1->id)->first();
    $purchaseItem = $purchase2->purchaseItems->first();

    // Update to $3.10 → 2.64
    $this->putJson(route('suppliers.purchases.update', $purchase2), [
        'final_total_usd' => 0, 'total_usd' => 0,
        'items' => [['id' => $purchaseItem->id, 'item_id' => $this->item1->id, 'price' => 3.10, 'quantity' => 50]],
    ])->assertOk();
    $itemPrice->refresh();
    expect((float) $itemPrice->price_usd)->toBe(2.64);

    // Update back to $3.00 → should return to 2.59
    $purchaseItem->refresh();
    $this->putJson(route('suppliers.purchases.update', $purchase2), [
        'final_total_usd' => 0, 'total_usd' => 0,
        'items' => [['id' => $purchaseItem->id, 'item_id' => $this->item1->id, 'price' => 3.00, 'quantity' => 50]],
    ])->assertOk();
    $itemPrice->refresh();
    expect((float) $itemPrice->price_usd)->toBe(2.59);
});

it('self-corrects weighted average when price data is corrupted', function () {
    $purchase1 = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 2.18, 'quantity' => 50]],
    ]);
    $purchase2 = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 3.00, 'quantity' => 50]],
    ]);

    $itemPrice = ItemPrice::where('item_id', $this->item1->id)->first();
    $itemPrice->update(['price_usd' => 2.4533]); // corrupt

    $purchaseItem = $purchase2->purchaseItems->first();
    $this->putJson(route('suppliers.purchases.update', $purchase2), [
        'final_total_usd' => 0, 'total_usd' => 0,
        'items' => [['id' => $purchaseItem->id, 'item_id' => $this->item1->id, 'price' => 3.00, 'quantity' => 51]],
    ])->assertOk();

    // (50*2.18 + 51*3.00) / 101 = 2.59 (rounded)
    $itemPrice->refresh();
    expect(round((float) $itemPrice->price_usd, 2))->toBe(2.59);
});

it('recalculates correctly when quantity changes in update', function () {
    $purchase1 = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 2.18, 'quantity' => 50]],
    ]);
    $purchase2 = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 3.00, 'quantity' => 50]],
    ]);

    $itemPrice    = ItemPrice::where('item_id', $this->item1->id)->first();
    $purchaseItem = $purchase2->purchaseItems->first();

    // Change qty from 50 → 100: (50*2.18 + 100*3.00) / 150 = 2.73
    $this->putJson(route('suppliers.purchases.update', $purchase2), [
        'final_total_usd' => 0, 'total_usd' => 0,
        'items' => [['id' => $purchaseItem->id, 'item_id' => $this->item1->id, 'price' => 3.00, 'quantity' => 100]],
    ])->assertOk();

    $itemPrice->refresh();
    expect(round((float) $itemPrice->price_usd, 2))->toBe(2.73);

    $inventory = Inventory::where('warehouse_id', $this->warehouse->id)->where('item_id', $this->item1->id)->first();
    expect((float) $inventory->quantity)->toBe(150.0);
});

it('calculates weighted average correctly with multiple purchases and updates', function () {
    $purchase1 = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 10.00, 'quantity' => 100]],
    ]);

    $itemPrice = ItemPrice::where('item_id', $this->item1->id)->first();
    expect((float) $itemPrice->price_usd)->toBe(10.00);

    // +50 @ $12 → avg = (100*10 + 50*12) / 150 = 10.67
    $purchase2 = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 12.00, 'quantity' => 50]],
    ]);
    $itemPrice->refresh();
    expect(round((float) $itemPrice->price_usd, 2))->toBe(10.67);

    // +50 @ $15 → avg ≈ 11.75
    $purchase3 = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 15.00, 'quantity' => 50]],
    ]);
    $itemPrice->refresh();
    expect(round((float) $itemPrice->price_usd, 1))->toBe(11.8);

    // Update purchase2: $12 → $14 → (100*10 + 50*14 + 50*15) / 200 = 12.25
    $purchaseItem2 = $purchase2->purchaseItems->first();
    $this->putJson(route('suppliers.purchases.update', $purchase2), [
        'final_total_usd' => 0, 'total_usd' => 0,
        'items' => [['id' => $purchaseItem2->id, 'item_id' => $this->item1->id, 'price' => 14.00, 'quantity' => 50]],
    ])->assertOk();

    $itemPrice->refresh();
    expect((float) $itemPrice->price_usd)->toBe(12.25);
});

it('creates item price history on significant price change', function () {
    ItemPrice::updateOrCreate(
        ['item_id' => $this->item1->id],
        ['price_usd' => 50.00, 'effective_date' => now()->toDateString()]
    );

    $response = $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
        'items' => [['item_id' => $this->item1->id, 'price' => 75.00, 'quantity' => 1]],
    ]))->assertCreated();

    $purchase = Purchase::find($response->json('data.id'));
    $this->patchJson(route('suppliers.purchases.changeStatus', $purchase), ['status' => 'Delivered'])->assertOk();

    $priceHistory = ItemPriceHistory::where('item_id', $this->item1->id)->first();
    expect($priceHistory)->not()->toBeNull()
        ->and($priceHistory->latest_price)->toBe('50.0000')
        ->and($priceHistory->price_usd)->toBeGreaterThan(50.00)
        ->and($priceHistory->source_type)->toBe('purchase');
});
