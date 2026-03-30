<?php

use App\Models\Items\ItemTransfer;
use Tests\Feature\Items\ItemTransfers\Concerns\HasItemTransferSetup;

uses(HasItemTransferSetup::class);

beforeEach(function () {
    $this->setUpItemTransfers();
});

it('soft deletes an item transfer', function () {
    $transfer = ItemTransfer::factory()->create(['from_warehouse_id' => $this->fromWarehouse->id, 'to_warehouse_id' => $this->toWarehouse->id]);

    $this->deleteJson(route('items.transfers.destroy', $transfer))->assertStatus(204);

    $this->assertSoftDeleted('item_transfers', ['id' => $transfer->id]);
});

it('lists trashed item transfers', function () {
    $transfer = ItemTransfer::factory()->create(['from_warehouse_id' => $this->fromWarehouse->id, 'to_warehouse_id' => $this->toWarehouse->id]);
    $transfer->delete();

    $this->getJson(route('items.transfers.trashed'))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('restores a trashed item transfer', function () {
    $transfer = ItemTransfer::factory()->create(['from_warehouse_id' => $this->fromWarehouse->id, 'to_warehouse_id' => $this->toWarehouse->id]);
    $transfer->delete();

    $this->patchJson(route('items.transfers.restore', $transfer->id))->assertOk();

    $this->assertDatabaseHas('item_transfers', ['id' => $transfer->id, 'deleted_at' => null]);
});

it('force deletes a trashed item transfer', function () {
    $transfer = ItemTransfer::factory()->create(['from_warehouse_id' => $this->fromWarehouse->id, 'to_warehouse_id' => $this->toWarehouse->id]);
    $transfer->delete();

    $this->deleteJson(route('items.transfers.force-delete', $transfer->id))->assertStatus(204);

    $this->assertDatabaseMissing('item_transfers', ['id' => $transfer->id]);
});
