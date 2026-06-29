<?php

use App\Models\Inventory\ItemPrice;
use App\Models\Inventory\ItemPriceHistory;
use App\Models\Items\Item;
use Tests\Feature\Calculations\ItemPrice\Concerns\HasItemPriceSetup;

uses(HasItemPriceSetup::class);
uses()->group('calculations', 'item-price');

// Tests: ItemPrice and ItemPriceHistory initialization when an item is created with a starting_price.
// Module: Items (setups.items.store)
// If the Item module is removed, delete this file.

beforeEach(function () {
    $this->setUpItemPrice();
});

it('creates ItemPrice and ItemPriceHistory when item is created with a starting_price', function () {
    $response = $this->postJson(route('setups.items.store'), $this->itemPayload([
        'starting_price'    => 80.00,
        'starting_quantity' => 100,
        'cost_calculation'  => Item::COST_WEIGHTED_AVERAGE,
    ]));

    $response->assertCreated();
    $itemId = $response->json('data.id');

    $itemPrice = ItemPrice::where('item_id', $itemId)->first();
    expect($itemPrice)->not()->toBeNull()
        ->and((float) $itemPrice->price_usd)->toBe(80.0);

    $history = ItemPriceHistory::where('item_id', $itemId)->first();
    expect($history)->not()->toBeNull()
        ->and((float) $history->price_usd)->toBe(80.0)
        ->and($history->source_type)->toBe('initial')
        ->and($history->is_current)->toBeTrue()
        ->and((float) $history->latest_price)->toBe(0.0);
});

it('does not create ItemPrice when starting_price is zero', function () {
    $response = $this->postJson(route('setups.items.store'), $this->itemPayload([
        'starting_price'    => 0,
        'starting_quantity' => 0,
    ]));

    $response->assertCreated();
    $itemId = $response->json('data.id');

    expect(ItemPrice::where('item_id', $itemId)->first())->toBeNull();
    expect(ItemPriceHistory::where('item_id', $itemId)->count())->toBe(0);
});

it('does not create ItemPrice when starting_price is omitted', function () {
    $payload = $this->itemPayload();
    unset($payload['starting_price']);

    $response = $this->postJson(route('setups.items.store'), $payload);

    $response->assertCreated();
    $itemId = $response->json('data.id');

    expect(ItemPrice::where('item_id', $itemId)->first())->toBeNull();
});

it('sets is_current true on the initial history entry', function () {
    $response = $this->postJson(route('setups.items.store'), $this->itemPayload([
        'starting_price' => 50.00,
    ]));

    $response->assertCreated();
    $itemId = $response->json('data.id');

    expect($this->currentHistoryCount($itemId))->toBe(1);
});

it('initial history entry has source_id pointing to the item itself', function () {
    $response = $this->postJson(route('setups.items.store'), $this->itemPayload([
        'starting_price' => 60.00,
    ]));

    $response->assertCreated();
    $itemId = $response->json('data.id');

    $history = ItemPriceHistory::where('item_id', $itemId)->first();
    expect($history->source_type)->toBe('initial')
        ->and($history->source_id)->toBe($itemId);
});

it('initial history entry has null calculation_type because initializeFromItem does not set it', function () {
    $response = $this->postJson(route('setups.items.store'), $this->itemPayload([
        'starting_price'   => 70.00,
        'cost_calculation' => Item::COST_LAST_COST,
    ]));

    $response->assertCreated();
    $itemId = $response->json('data.id');

    $history = ItemPriceHistory::where('item_id', $itemId)->first();
    expect($history->calculation_type)->toBeNull();
});
