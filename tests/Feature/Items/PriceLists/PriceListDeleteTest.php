<?php

use Tests\Feature\Items\PriceLists\Concerns\HasPriceListSetup;

uses(HasPriceListSetup::class);

uses()->group('api', 'items', 'price-lists');

beforeEach(function () {
    $this->setUpPriceLists();
});

it('soft deletes a price list', function () {
    $priceList = $this->createPriceList();

    $this->deleteJson(route('setups.price-lists.destroy', $priceList))->assertNoContent();

    $this->assertSoftDeleted('price_lists', ['id' => $priceList->id]);
});

it('cascades soft delete to price list items', function () {
    $priceList = $this->createPriceList();
    $item      = $this->createPriceListItem($priceList);

    $priceList->delete();

    $this->assertSoftDeleted('price_list_items', ['id' => $item->id]);
});

it('lists trashed price lists', function () {
    $priceList = $this->createPriceList();
    $priceList->delete();

    $this->getJson(route('setups.price-lists.trashed'))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data'       => ['*' => ['id', 'code', 'description', 'item_count']],
            'pagination',
        ])
        ->assertJsonCount(1, 'data');
});

it('restores a trashed price list', function () {
    $priceList = $this->createPriceList();
    $priceList->delete();

    $this->patchJson(route('setups.price-lists.restore', $priceList->id))->assertOk();

    $this->assertDatabaseHas('price_lists', ['id' => $priceList->id, 'deleted_at' => null]);
});

it('force deletes a trashed price list', function () {
    $priceList = $this->createPriceList();
    $priceList->delete();

    $this->deleteJson(route('setups.price-lists.force-delete', $priceList->id))->assertNoContent();

    $this->assertDatabaseMissing('price_lists', ['id' => $priceList->id]);
});
