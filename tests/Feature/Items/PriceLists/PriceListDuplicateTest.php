<?php

use Tests\Feature\Items\PriceLists\Concerns\HasPriceListSetup;

uses(HasPriceListSetup::class);

uses()->group('api', 'items', 'price-lists');

beforeEach(function () {
    $this->setUpPriceLists();
});

it('duplicates a price list with its items', function () {
    $priceList = $this->createPriceList([
        'code'        => 'PL-ORIGINAL',
        'description' => 'Original Price List',
    ]);
    $this->createPriceListItem($priceList);
    $this->createPriceListItem($priceList);

    $duplicated = $this->postJson(route('setups.price-lists.duplicate', $priceList))
        ->assertCreated()
        ->assertJsonStructure([
            'message',
            'data' => ['id', 'code', 'description', 'item_count'],
        ])
        ->json('data');

    expect($duplicated['code'])->toBe('PL-ORIGINAL-COPY')
        ->and($duplicated['description'])->toBe('Original Price List (Copy)')
        ->and($duplicated['item_count'])->toBe(2);
});
