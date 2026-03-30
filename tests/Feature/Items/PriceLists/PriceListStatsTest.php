<?php

use App\Models\Items\PriceList;
use App\Models\Items\PriceListItem;
use Tests\Feature\Items\PriceLists\Concerns\HasPriceListSetup;

uses(HasPriceListSetup::class);

uses()->group('api', 'items', 'price-lists');

beforeEach(function () {
    $this->setUpPriceLists();
});

it('returns price list statistics with correct structure and values', function () {
    PriceList::factory()->count(5)->create([
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ])->each(function ($priceList) {
        PriceListItem::factory()->count(3)->create([
            'price_list_id' => $priceList->id,
            'created_by'    => $this->admin->id,
            'updated_by'    => $this->admin->id,
        ]);
    });

    $stats = $this->getJson(route('setups.price-lists.stats'))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'total_price_lists',
                'trashed_price_lists',
                'total_items',
                'average_items_per_list',
                'recent_price_lists',
            ],
        ])
        ->json('data');

    expect($stats['total_price_lists'])->toBe(5)
        ->and($stats['total_items'])->toBe(15);
});
