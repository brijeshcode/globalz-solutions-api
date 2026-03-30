<?php

use App\Models\Inventory\Inventory;
use Tests\Feature\Items\ItemAdjusts\Concerns\HasItemAdjustSetup;

uses(HasItemAdjustSetup::class);

beforeEach(function () {
    $this->setUpItemAdjusts();
    $this->actingAsAdmin();
});

it('updates item adjust with item sync and corrects inventory', function () {
    $this->setupInventory($this->item1->id, 100);
    $this->setupInventory($this->item2->id, 200);

    $adjust       = $this->createAdjustViaApi(['type' => 'Add', 'items' => [['item_id' => $this->item1->id, 'quantity' => 5]]]);
    $existingItem = $adjust->itemAdjustItems->first();

    $this->putJson(route('items.adjusts.update', $adjust), [
        'items' => [
            ['id' => $existingItem->id, 'item_id' => $this->item1->id, 'quantity' => 8, 'note' => 'Updated item'],
            ['item_id' => $this->item2->id, 'quantity' => 10, 'note' => 'New item added'],
        ],
    ])->assertOk();

    $adjust->refresh();
    expect($adjust->itemAdjustItems)->toHaveCount(2);

    $existingItem->refresh();
    expect($existingItem->quantity)->toBe('8.0000');

    // Initial:100, +5, update to 8 (+3 more) = 108
    expect(Inventory::where('warehouse_id', $this->warehouse->id)->where('item_id', $this->item1->id)->first()->quantity)->toBe('108.0000');
    // Initial:200, +10 = 210
    expect(Inventory::where('warehouse_id', $this->warehouse->id)->where('item_id', $this->item2->id)->first()->quantity)->toBe('210.0000');
});
