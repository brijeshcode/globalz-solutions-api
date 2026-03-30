<?php

use App\Models\Inventory\Inventory;
use Tests\Feature\Items\ItemTransfers\Concerns\HasItemTransferSetup;

uses(HasItemTransferSetup::class);

beforeEach(function () {
    $this->setUpItemTransfers();
});

it('updates item transfer with item sync and corrects inventory in both warehouses', function () {
    $this->setupInventory($this->item1->id, $this->fromWarehouse->id, 100);
    $this->setupInventory($this->item2->id, $this->fromWarehouse->id, 200);

    $transfer     = $this->createTransferViaApi(['items' => [['item_id' => $this->item1->id, 'quantity' => 5]]]);
    $existingItem = $transfer->itemTransferItems->first();

    $this->putJson(route('items.transfers.update', $transfer), [
        'items' => [
            ['id' => $existingItem->id, 'item_id' => $this->item1->id, 'quantity' => 8, 'note' => 'Updated item'],
            ['item_id' => $this->item2->id, 'quantity' => 10, 'note' => 'New item added'],
        ],
    ])->assertOk();

    $transfer->refresh();
    expect($transfer->itemTransferItems)->toHaveCount(2);

    $existingItem->refresh();
    expect($existingItem->quantity)->toBe('8.0000');

    // Source: 100, -5, update to 8 (-3 more) = 92
    expect(Inventory::where('warehouse_id', $this->fromWarehouse->id)->where('item_id', $this->item1->id)->first()->quantity)->toBe('92.0000');
    // Dest: 0, +5, update to 8 (+3 more) = 8
    expect(Inventory::where('warehouse_id', $this->toWarehouse->id)->where('item_id', $this->item1->id)->first()->quantity)->toBe('8.0000');
});
