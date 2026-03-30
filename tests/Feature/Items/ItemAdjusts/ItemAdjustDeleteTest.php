<?php

use App\Models\Items\ItemAdjust;
use Tests\Feature\Items\ItemAdjusts\Concerns\HasItemAdjustSetup;

uses(HasItemAdjustSetup::class);

beforeEach(function () {
    $this->setUpItemAdjusts();
    $this->actingAsAdmin();
});

it('soft deletes an item adjust', function () {
    $adjust = ItemAdjust::factory()->create(['warehouse_id' => $this->warehouse->id]);

    $this->deleteJson(route('items.adjusts.destroy', $adjust))->assertStatus(204);

    $this->assertSoftDeleted('item_adjusts', ['id' => $adjust->id]);
});

it('lists trashed item adjusts', function () {
    $adjust = ItemAdjust::factory()->create(['warehouse_id' => $this->warehouse->id]);
    $adjust->delete();

    $this->getJson(route('items.adjusts.trashed'))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('restores a trashed item adjust', function () {
    $adjust = ItemAdjust::factory()->create(['warehouse_id' => $this->warehouse->id]);
    $adjust->delete();

    $this->patchJson(route('items.adjusts.restore', $adjust->id))->assertOk();

    $this->assertDatabaseHas('item_adjusts', ['id' => $adjust->id, 'deleted_at' => null]);
});

it('force deletes a trashed item adjust', function () {
    $adjust = ItemAdjust::factory()->create(['warehouse_id' => $this->warehouse->id]);
    $adjust->delete();

    $this->deleteJson(route('items.adjusts.force-delete', $adjust->id))->assertStatus(204);

    $this->assertDatabaseMissing('item_adjusts', ['id' => $adjust->id]);
});
