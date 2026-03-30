<?php

use App\Models\Inventory\Inventory;
use App\Models\Items\ItemTransfer;
use Tests\Feature\Items\ItemTransfers\Concerns\HasItemTransferSetup;

uses(HasItemTransferSetup::class);

beforeEach(function () {
    $this->setUpItemTransfers();
});

it('creates an item transfer and updates inventory in both warehouses', function () {
    $this->setupInventory($this->item1->id, $this->fromWarehouse->id, 100);
    $this->setupInventory($this->item2->id, $this->fromWarehouse->id, 200);

    $this->postJson(route('items.transfers.store'), $this->transferPayload([
        'note'  => 'Test transfer with multiple items',
        'items' => [
            ['item_id' => $this->item1->id, 'quantity' => 10, 'note' => 'First transferred item'],
            ['item_id' => $this->item2->id, 'quantity' => 15, 'note' => 'Second transferred item'],
        ],
    ]))
        ->assertCreated()
        ->assertJsonStructure(['message', 'data' => ['id', 'code', 'items', 'from_warehouse', 'to_warehouse']]);

    $this->assertDatabaseHas('item_transfers', ['from_warehouse_id' => $this->fromWarehouse->id, 'to_warehouse_id' => $this->toWarehouse->id]);

    $transfer = ItemTransfer::latest()->first();
    expect($transfer->itemTransferItems)->toHaveCount(2);

    // Source warehouse reduced
    expect(Inventory::where('warehouse_id', $this->fromWarehouse->id)->where('item_id', $this->item1->id)->first()->quantity)->toBe('90.0000');
    expect(Inventory::where('warehouse_id', $this->fromWarehouse->id)->where('item_id', $this->item2->id)->first()->quantity)->toBe('185.0000');

    // Destination warehouse increased
    expect(Inventory::where('warehouse_id', $this->toWarehouse->id)->where('item_id', $this->item1->id)->first()->quantity)->toBe('10.0000');
    expect(Inventory::where('warehouse_id', $this->toWarehouse->id)->where('item_id', $this->item2->id)->first()->quantity)->toBe('15.0000');
});

it('auto-generates code with TRAN prefix', function () {
    $this->setupInventory($this->item1->id, $this->fromWarehouse->id, 50);

    $this->postJson(route('items.transfers.store'), $this->transferPayload())->assertCreated();

    $transfer = ItemTransfer::where('from_warehouse_id', $this->fromWarehouse->id)->first();
    expect($transfer->code)->not()->toBeNull()
        ->and($transfer->prefix)->toBe('TRAN');
});

it('validates required fields', function () {
    $this->postJson(route('items.transfers.store'), [
        'from_warehouse_id' => 999,
        'to_warehouse_id'   => null,
        'items'             => [['item_id' => 999, 'quantity' => 0]],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['from_warehouse_id', 'to_warehouse_id', 'items.0.item_id', 'items.0.quantity']);
});

it('validates from and to warehouses must be different', function () {
    $this->setupInventory($this->item1->id, $this->fromWarehouse->id, 50);

    $this->postJson(route('items.transfers.store'), $this->transferPayload([
        'from_warehouse_id' => $this->fromWarehouse->id,
        'to_warehouse_id'   => $this->fromWarehouse->id,
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to_warehouse_id']);
});

it('sets created_by and updated_by automatically', function () {
    $transfer = ItemTransfer::factory()->create(['from_warehouse_id' => $this->fromWarehouse->id, 'to_warehouse_id' => $this->toWarehouse->id]);

    expect($transfer->created_by)->not()->toBeNull()
        ->and($transfer->updated_by)->not()->toBeNull();

    $transfer->update(['note' => 'Updated note']);
    expect($transfer->fresh()->updated_by)->not()->toBeNull();
});
