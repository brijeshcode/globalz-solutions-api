<?php

use App\Models\Inventory\Inventory;
use App\Models\Inventory\ItemPrice;
use App\Models\Inventory\ItemPriceHistory;
use App\Models\Items\Item;
use App\Models\Setups\Warehouse;
use App\Models\Suppliers\SupplierItemPrice;
use Tests\Feature\Items\Items\Concerns\HasItemSetup;

uses(HasItemSetup::class);

beforeEach(function () {
    $this->setUpItems();
});

it('creates an item with minimum required fields', function () {
    $this->postJson(route('setups.items.store'), $this->itemPayload(['short_name' => 'Test Item', 'is_active' => true]))
        ->assertCreated()
        ->assertJsonStructure(['message', 'data' => ['id', 'code', 'short_name', 'base_cost', 'base_sell', 'is_active']]);

    $this->assertDatabaseHas('items', ['short_name' => 'Test Item', 'base_cost' => 100.00, 'base_sell' => 120.00, 'is_active' => true]);

    $item = Item::where('short_name', 'Test Item')->first();
    expect((int) $item->code)->toBeGreaterThanOrEqual(5000);
});

it('creates an item with all fields', function () {
    $this->postJson(route('setups.items.store'), $this->itemPayload([
        'short_name'          => 'Complete Item',
        'description'         => 'A complete test item with all fields',
        'item_group_id'       => $this->itemGroup->id,
        'item_category_id'    => $this->itemCategory->id,
        'item_brand_id'       => $this->itemBrand->id,
        'supplier_id'         => $this->supplier->id,
        'base_cost'           => 150.75,
        'base_sell'           => 200.50,
        'starting_price'      => 180.00,
        'starting_quantity'   => 100,
        'low_quantity_alert'  => 10,
        'cost_calculation'    => 'weighted_average',
        'is_active'           => true,
    ]))
        ->assertCreated()
        ->assertJson(['data' => ['short_name' => 'Complete Item', 'base_cost' => 150.75, 'base_sell' => 200.50, 'starting_quantity' => 100, 'cost_calculation' => 'weighted_average']]);

    $item = Item::where('short_name', 'Complete Item')->first();
    expect($item->code)->not()->toBeNull()
        ->and((int) $item->code)->toBeGreaterThanOrEqual(5000);
});

it('creates an item with a custom code', function () {
    $this->postJson(route('setups.items.store'), $this->itemPayload(['code' => 'CUSTOM-5000', 'short_name' => 'Custom Code Item']))
        ->assertCreated()
        ->assertJson(['data' => ['code' => 'CUSTOM-5000', 'short_name' => 'Custom Code Item']]);

    $this->assertDatabaseHas('items', ['code' => 'CUSTOM-5000']);
});

it('auto-generates code when not provided', function () {
    $response = $this->postJson(route('setups.items.store'), $this->itemPayload(['short_name' => 'Auto Code Item']))->assertCreated();

    $code = $response->json('data.code');
    expect($code)->not()->toBeNull()
        ->and((int) $code)->toBeGreaterThanOrEqual(5000);

    $this->assertDatabaseHas('items', ['short_name' => 'Auto Code Item', 'code' => $code]);
});

it('sets created_by and updated_by automatically', function () {
    $item = $this->createItem(['short_name' => 'Test Item']);

    expect($item->created_by)->toBe($this->admin->id)
        ->and($item->updated_by)->toBe($this->admin->id);

    $item->update(['short_name' => 'Updated Item']);
    expect($item->fresh()->updated_by)->toBe($this->admin->id);
});

it('validates required fields', function () {
    $this->postJson(route('setups.items.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['description', 'item_unit_id', 'tax_code_id']);
});

it('validates foreign key references', function () {
    $this->postJson(route('setups.items.store'), $this->itemPayload([
        'item_type_id'     => 99999,
        'item_family_id'   => 99999,
        'item_group_id'    => 99999,
        'item_category_id' => 99999,
        'item_brand_id'    => 99999,
        'item_unit_id'     => 99999,
        'supplier_id'      => 99999,
        'tax_code_id'      => 99999,
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['item_type_id', 'item_family_id', 'item_group_id', 'item_category_id', 'item_brand_id', 'item_unit_id', 'supplier_id', 'tax_code_id']);
});

it('validates unique code constraint', function () {
    $this->createItem(['code' => 'DUPLICATE-CODE']);

    $this->postJson(route('setups.items.store'), $this->itemPayload(['code' => 'DUPLICATE-CODE', 'short_name' => 'Duplicate Item']))
        ->assertUnprocessable();
});

it('validates cost_calculation enum values', function () {
    $this->postJson(route('setups.items.store'), $this->itemPayload(['cost_calculation' => 'INVALID_METHOD']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cost_calculation']);
});

it('validates numeric fields must be positive', function () {
    $this->postJson(route('setups.items.store'), $this->itemPayload([
        'base_cost'          => -50.00,
        'base_sell'          => -60.00,
        'starting_price'     => -10.00,
        'starting_quantity'  => -5,
        'low_quantity_alert' => -1,
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['base_cost', 'base_sell', 'starting_price', 'starting_quantity', 'low_quantity_alert']);
});

it('creates inventory record from starting_quantity', function () {
    $warehouse = Warehouse::factory()->create(['is_default' => true]);

    $response = $this->postJson(route('setups.items.store'), $this->itemPayload(['starting_quantity' => 100]))->assertCreated();

    $item      = Item::where('short_name', $this->itemPayload()['short_name'])->first();
    $inventory = Inventory::where('item_id', $item->id)->where('warehouse_id', $warehouse->id)->first();

    expect($inventory)->not()->toBeNull()
        ->and((float) $inventory->quantity)->toBe(100.0);
});

it('creates item price record from starting_price', function () {
    $response = $this->postJson(route('setups.items.store'), $this->itemPayload(['starting_price' => 150.75]))->assertCreated();

    $item      = Item::find($response->json('data.id'));
    $itemPrice = ItemPrice::where('item_id', $item->id)->first();

    expect($itemPrice)->not()->toBeNull()
        ->and((float) $itemPrice->price_usd)->toBe(150.75)
        ->and($itemPrice->effective_date->format('Y-m-d'))->toBe($item->created_at->format('Y-m-d'));
});

it('creates supplier item price from base_cost', function () {
    $response = $this->postJson(route('setups.items.store'), $this->itemPayload(['base_cost' => 85.50, 'supplier_id' => $this->supplier->id]))->assertCreated();

    $item              = Item::find($response->json('data.id'));
    $supplierItemPrice = SupplierItemPrice::where('item_id', $item->id)->where('supplier_id', $this->supplier->id)->where('is_current', true)->first();

    expect($supplierItemPrice)->not()->toBeNull()
        ->and((float) $supplierItemPrice->price)->toBe(85.50)
        ->and($supplierItemPrice->note)->toBe('Initialized from item base cost');
});

it('creates item price history as initial price', function () {
    $response = $this->postJson(route('setups.items.store'), $this->itemPayload(['starting_price' => 125.00]))->assertCreated();

    $item         = Item::find($response->json('data.id'));
    $priceHistory = ItemPriceHistory::where('item_id', $item->id)->where('source_type', 'initial')->first();

    expect($priceHistory)->not()->toBeNull()
        ->and((float) $priceHistory->price_usd)->toBe(125.00)
        ->and((float) $priceHistory->average_waited_price)->toBe(125.00)
        ->and((float) $priceHistory->latest_price)->toBe(0.0)
        ->and($priceHistory->note)->toBe('Initial price from item creation');
});
