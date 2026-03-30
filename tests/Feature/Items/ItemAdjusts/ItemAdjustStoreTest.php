<?php

use App\Models\Inventory\Inventory;
use App\Models\Items\ItemAdjust;
use Tests\Feature\Items\ItemAdjusts\Concerns\HasItemAdjustSetup;

uses(HasItemAdjustSetup::class);

beforeEach(function () {
    $this->setUpItemAdjusts();
    $this->actingAsAdmin();
});

it('creates Add adjustment and increases inventory', function () {
    $this->setupInventory($this->item1->id, 100);
    $this->setupInventory($this->item2->id, 200);

    $this->postJson(route('items.adjusts.store'), $this->adjustPayload([
        'type' => 'Add',
        'note' => 'Test adjustment with multiple items',
        'items' => [
            ['item_id' => $this->item1->id, 'quantity' => 10, 'note' => 'First adjusted item'],
            ['item_id' => $this->item2->id, 'quantity' => 15, 'note' => 'Second adjusted item'],
        ],
    ]))
        ->assertCreated()
        ->assertJsonStructure(['message', 'data' => ['id', 'code', 'type', 'items', 'warehouse']]);

    $this->assertDatabaseHas('item_adjusts', ['warehouse_id' => $this->warehouse->id, 'type' => 'Add']);

    $adjust = ItemAdjust::latest()->first();
    expect($adjust->itemAdjustItems)->toHaveCount(2);

    expect(Inventory::where('warehouse_id', $this->warehouse->id)->where('item_id', $this->item1->id)->first()->quantity)->toBe('110.0000');
    expect(Inventory::where('warehouse_id', $this->warehouse->id)->where('item_id', $this->item2->id)->first()->quantity)->toBe('215.0000');
});

it('creates Subtract adjustment and decreases inventory', function () {
    $this->setupInventory($this->item1->id, 100);
    $this->setupInventory($this->item2->id, 200);

    $this->postJson(route('items.adjusts.store'), $this->adjustPayload([
        'type'  => 'Subtract',
        'items' => [
            ['item_id' => $this->item1->id, 'quantity' => 10],
            ['item_id' => $this->item2->id, 'quantity' => 15],
        ],
    ]))->assertCreated();

    expect(Inventory::where('warehouse_id', $this->warehouse->id)->where('item_id', $this->item1->id)->first()->quantity)->toBe('90.0000');
    expect(Inventory::where('warehouse_id', $this->warehouse->id)->where('item_id', $this->item2->id)->first()->quantity)->toBe('185.0000');
});

it('auto-generates code with ADJ prefix', function () {
    $this->setupInventory($this->item1->id, 50);

    $this->postJson(route('items.adjusts.store'), $this->adjustPayload())->assertCreated();

    $adjust = ItemAdjust::where('warehouse_id', $this->warehouse->id)->first();
    expect($adjust->code)->not()->toBeNull()
        ->and($adjust->prefix)->toBe('ADJ');
});

it('validates required fields', function () {
    $this->postJson(route('items.adjusts.store'), [
        'warehouse_id' => 999,
        'type'         => null,
        'items'        => [['item_id' => 999, 'quantity' => 0]],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['warehouse_id', 'type', 'items.0.item_id', 'items.0.quantity']);
});

it('validates type must be Add or Subtract', function () {
    $this->setupInventory($this->item1->id, 50);

    $this->postJson(route('items.adjusts.store'), $this->adjustPayload(['type' => 'Invalid']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

it('sets created_by and updated_by automatically', function () {
    $this->setupInventory($this->item1->id, 50);

    $adjust = ItemAdjust::factory()->create(['warehouse_id' => $this->warehouse->id]);

    expect($adjust->created_by)->not()->toBeNull()
        ->and($adjust->updated_by)->not()->toBeNull();
});
