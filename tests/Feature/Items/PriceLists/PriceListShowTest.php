<?php

use Tests\Feature\Items\PriceLists\Concerns\HasPriceListSetup;

uses(HasPriceListSetup::class);

uses()->group('api', 'items', 'price-lists');

beforeEach(function () {
    $this->setUpPriceLists();
});

it('shows a price list with items', function () {
    $priceList = $this->createPriceList();
    $this->createPriceListItem($priceList);
    $this->createPriceListItem($priceList);

    $this->getJson(route('setups.price-lists.show', $priceList))
        ->assertOk()
        ->assertJson([
            'data' => [
                'id'          => $priceList->id,
                'code'        => $priceList->code,
                'description' => $priceList->description,
            ],
        ])
        ->assertJsonStructure([
            'data' => [
                'items' => ['*' => ['id', 'item_code', 'sell_price']],
            ],
        ]);
});

it('returns 404 for a non-existent price list', function () {
    $this->getJson(route('setups.price-lists.show', 999))->assertNotFound();
});
