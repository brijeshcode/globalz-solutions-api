<?php

use App\Models\Items\PriceList;
use Tests\Feature\Items\PriceLists\Concerns\HasPriceListSetup;

uses(HasPriceListSetup::class);

uses()->group('api', 'items', 'price-lists');

beforeEach(function () {
    $this->setUpPriceLists();
});

it('creates a price list with items', function () {
    $data = $this->priceListPayload();

    $this->postJson(route('setups.price-lists.store'), $data)
        ->assertCreated()
        ->assertJsonStructure([
            'message',
            'data' => ['id', 'code', 'description', 'item_count', 'items'],
        ]);

    $this->assertDatabaseHas('price_lists', [
        'code'        => $data['code'],
        'description' => 'Test Price List',
        'item_count'  => 2,
    ]);

    $priceList = PriceList::where('code', $data['code'])->first();
    expect($priceList->items)->toHaveCount(2);
});

it('creates a price list with minimum required fields', function () {
    $data = [
        'code'        => 'PL-MIN',
        'description' => 'Minimal Price List',
        'items'       => [
            [
                'item_code'  => $this->item1->code,
                'item_id'    => $this->item1->id,
                'sell_price' => 150.00,
            ],
        ],
    ];

    $this->postJson(route('setups.price-lists.store'), $data)
        ->assertCreated()
        ->assertJson([
            'data' => ['code' => 'PL-MIN', 'description' => 'Minimal Price List', 'item_count' => 1],
        ]);
});

it('auto-populates item details when item_id is provided', function () {
    $data = [
        'code'        => 'PL-AUTO',
        'description' => 'Auto Populate Test',
        'items'       => [
            [
                'item_id'    => $this->item1->id,
                'sell_price' => 125.00,
            ],
        ],
    ];

    $this->postJson(route('setups.price-lists.store'), $data)->assertCreated();

    $priceList = PriceList::where('code', 'PL-AUTO')->first();
    $item      = $priceList->items->first();

    expect($item->item_code)->toBe($this->item1->code)
        ->and($item->item_description)->toBe($this->item1->description);
});

it('updates item_count automatically when items are added', function () {
    $data     = $this->priceListPayload();
    $response = $this->postJson(route('setups.price-lists.store'), $data)->assertCreated();

    $priceList = PriceList::find($response->json('data.id'));
    expect($priceList->item_count)->toBe(2);
});

it('sets created_by and updated_by automatically', function () {
    $data      = $this->priceListPayload();
    $response  = $this->postJson(route('setups.price-lists.store'), $data)->assertCreated();

    $priceList = PriceList::find($response->json('data.id'));
    expect($priceList->created_by)->toBe($this->admin->id)
        ->and($priceList->updated_by)->toBe($this->admin->id);
});

it('validates required fields', function () {
    $this->postJson(route('setups.price-lists.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code', 'description', 'items']);
});

it('validates unique code constraint', function () {
    $this->createPriceList(['code' => 'DUPLICATE-CODE']);

    $this->postJson(route('setups.price-lists.store'), $this->priceListPayload(['code' => 'DUPLICATE-CODE']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

it('validates items array is not empty', function () {
    $this->postJson(route('setups.price-lists.store'), [
        'code'        => 'PL-EMPTY',
        'description' => 'Empty Price List',
        'items'       => [],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items']);
});

it('validates item sell_price is required and numeric', function () {
    $this->postJson(route('setups.price-lists.store'), [
        'code'        => 'PL-TEST',
        'description' => 'Test Price List',
        'items'       => [
            [
                'item_code' => $this->item1->code,
                'item_id'   => $this->item1->id,
                // Missing sell_price
            ],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.sell_price']);
});

it('validates foreign key references for items', function () {
    $this->postJson(route('setups.price-lists.store'), [
        'code'        => 'PL-TEST',
        'description' => 'Test Price List',
        'items'       => [
            [
                'item_code'  => 'INVALID',
                'item_id'    => 99999,
                'sell_price' => 100.00,
            ],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.item_id']);
});
