<?php

use App\Models\Items\PriceListItem;
use Tests\Feature\Items\PriceLists\Concerns\HasPriceListSetup;

uses(HasPriceListSetup::class);

uses()->group('api', 'items', 'price-lists');

beforeEach(function () {
    $this->setUpPriceLists();
});

it('updates a price list description and note', function () {
    $priceList = $this->createPriceList();

    $this->putJson(route('setups.price-lists.update', $priceList), [
        'description' => 'Updated Price List',
        'note'        => 'Updated note',
    ])
        ->assertOk()
        ->assertJson([
            'data' => ['description' => 'Updated Price List', 'note' => 'Updated note'],
        ]);

    $this->assertDatabaseHas('price_lists', [
        'id'          => $priceList->id,
        'description' => 'Updated Price List',
    ]);
});

it('updates existing price list items and adds new ones', function () {
    $priceList    = $this->createPriceList();
    $existingItem = $this->createPriceListItem($priceList, [
        'item_id'    => $this->item1->id,
        'sell_price' => 100.00,
    ]);

    $this->putJson(route('setups.price-lists.update', $priceList), [
        'items' => [
            [
                'id'         => $existingItem->id,
                'item_code'  => $this->item1->code,
                'item_id'    => $this->item1->id,
                'sell_price' => 150.00,
            ],
            [
                'item_code'  => $this->item2->code,
                'item_id'    => $this->item2->id,
                'sell_price' => 250.00,
            ],
        ],
    ])->assertOk();

    $this->assertDatabaseHas('price_list_items', [
        'id'         => $existingItem->id,
        'sell_price' => 150.00,
    ]);

    $this->assertDatabaseHas('price_list_items', [
        'price_list_id' => $priceList->id,
        'item_id'       => $this->item2->id,
        'sell_price'    => 250.00,
    ]);
});

it('updates item_count when items are removed', function () {
    $priceList = $this->createPriceList();
    $item1     = $this->createPriceListItem($priceList);
    $item2     = $this->createPriceListItem($priceList);

    expect($priceList->fresh()->item_count)->toBe(2);

    $item1->delete();
    $priceList->updateItemCount();

    expect($priceList->fresh()->item_count)->toBe(1);
});
