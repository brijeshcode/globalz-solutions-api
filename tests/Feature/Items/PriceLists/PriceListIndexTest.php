<?php

use App\Models\Items\PriceList;
use Tests\Feature\Items\PriceLists\Concerns\HasPriceListSetup;

uses(HasPriceListSetup::class);

uses()->group('api', 'items', 'price-lists');

beforeEach(function () {
    $this->setUpPriceLists();
});

it('lists price lists with correct structure', function () {
    PriceList::factory()->count(3)->create([
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $this->getJson(route('setups.price-lists.index'))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => ['*' => ['id', 'code', 'description', 'item_count']],
            'pagination',
        ])
        ->assertJsonCount(3, 'data');
});

it('searches by code', function () {
    $this->createPriceList(['code' => 'SEARCH-001', 'description' => 'First Price List']);
    $this->createPriceList(['code' => 'SEARCH-002', 'description' => 'Second Price List']);

    $data = $this->getJson(route('setups.price-lists.index', ['search' => 'SEARCH-001']))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['code'])->toBe('SEARCH-001');
});

it('searches by description', function () {
    $this->createPriceList(['description' => 'Wholesale Prices']);
    $this->createPriceList(['description' => 'Retail Prices']);

    $data = $this->getJson(route('setups.price-lists.index', ['search' => 'Wholesale']))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['description'])->toContain('Wholesale');
});

it('filters by code', function () {
    $this->createPriceList(['code' => 'PL-FILTER']);
    $this->createPriceList(['code' => 'PL-OTHER']);

    $data = $this->getJson(route('setups.price-lists.index', ['code' => 'PL-FILTER']))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['code'])->toBe('PL-FILTER');
});

it('sorts by code ascending', function () {
    $this->createPriceList(['code' => 'PL-Z']);
    $this->createPriceList(['code' => 'PL-A']);

    $data = $this->getJson(route('setups.price-lists.index', ['sort_by' => 'code', 'sort_direction' => 'asc']))
        ->assertOk()
        ->json('data');

    expect($data[0]['code'])->toBe('PL-A')
        ->and($data[1]['code'])->toBe('PL-Z');
});

it('paginates results', function () {
    PriceList::factory()->count(7)->create([
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $response = $this->getJson(route('setups.price-lists.index', ['per_page' => 3]))->assertOk();

    expect($response->json('data'))->toHaveCount(3)
        ->and($response->json('pagination.total'))->toBe(7)
        ->and($response->json('pagination.per_page'))->toBe(3)
        ->and($response->json('pagination.last_page'))->toBe(3);
});
