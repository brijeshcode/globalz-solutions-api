<?php

use Tests\Feature\Items\Items\Concerns\HasItemSetup;

uses(HasItemSetup::class);

beforeEach(function () {
    $this->setUpItems();
});

it('soft deletes an item', function () {
    $item = $this->createItem();

    $this->deleteJson(route('setups.items.destroy', $item))->assertNoContent();

    $this->assertSoftDeleted('items', ['id' => $item->id]);
});

it('lists trashed items', function () {
    $item = $this->createItem();
    $item->delete();

    $this->getJson(route('setups.items.trashed'))
        ->assertOk()
        ->assertJsonStructure(['message', 'data' => ['*' => ['id', 'code', 'short_name', 'item_type', 'item_family', 'is_active']], 'pagination'])
        ->assertJsonCount(1, 'data');
});

it('restores a trashed item', function () {
    $item = $this->createItem();
    $item->delete();

    $this->patchJson(route('setups.items.restore', $item->id))->assertOk();

    $this->assertDatabaseHas('items', ['id' => $item->id, 'deleted_at' => null]);
});

it('force deletes a trashed item', function () {
    $item = $this->createItem();
    $item->delete();

    $this->deleteJson(route('setups.items.force-delete', $item->id))->assertNoContent();

    $this->assertDatabaseMissing('items', ['id' => $item->id]);
});
