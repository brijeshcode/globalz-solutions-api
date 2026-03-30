<?php

use App\Models\Items\Item;
use App\Models\Setups\ItemType;
use Tests\Feature\Items\Items\Concerns\HasItemSetup;

uses(HasItemSetup::class);

beforeEach(function () {
    $this->setUpItems();
});

it('lists items with correct structure', function () {
    $this->createItem();
    $this->createItem();
    $this->createItem();

    $this->getJson(route('setups.items.index'))
        ->assertOk()
        ->assertJsonStructure(['message', 'data' => ['*' => ['id', 'code', 'short_name', 'description']], 'pagination'])
        ->assertJsonCount(3, 'data');
});

it('searches by short name', function () {
    $this->createItem(['short_name' => 'Searchable Item']);
    $this->createItem(['short_name' => 'Another Item']);

    $data = $this->getJson(route('setups.items.index', ['search' => 'Searchable']))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['short_name'])->toBe('Searchable Item');
});

it('searches by code', function () {
    $this->createItem(['code' => 'SEARCH-001', 'short_name' => 'First Item']);
    $this->createItem(['code' => 'SEARCH-002', 'short_name' => 'Second Item']);

    $data = $this->getJson(route('setups.items.index', ['search' => 'SEARCH-001']))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['code'])->toBe('SEARCH-001');
});

it('filters by active status', function () {
    $this->createItem(['is_active' => true]);
    $this->createItem(['is_active' => false]);

    $data = $this->getJson(route('setups.items.index', ['is_active' => true]))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['is_active'])->toBe(true);
});

it('filters by item type', function () {
    $productType = ItemType::factory()->create(['name' => 'Product']);
    $serviceType = ItemType::factory()->create(['name' => 'Service']);

    $this->createItem(['item_type_id' => $productType->id]);
    $this->createItem(['item_type_id' => $serviceType->id]);

    $data = $this->getJson(route('setups.items.index', ['item_type_id' => $productType->id]))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['item_type']['id'])->toBe($productType->id);
});

it('filters by price range', function () {
    $this->createItem(['base_sell' => 50.00]);
    $this->createItem(['base_sell' => 150.00]);
    $this->createItem(['base_sell' => 250.00]);

    $data = $this->getJson(route('setups.items.index', ['min_price' => 100.00, 'max_price' => 200.00]))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['base_sell'])->toBe(150);
});

it('filters by low stock', function () {
    $this->createItem(['starting_quantity' => 100, 'low_quantity_alert' => 10]);
    $this->createItem(['starting_quantity' => 5, 'low_quantity_alert' => 10]);

    $data = $this->getJson(route('setups.items.index', ['low_stock' => true]))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['starting_quantity'])->toBe(5);
});

it('sorts by short name ascending', function () {
    $this->createItem(['short_name' => 'Z Item']);
    $this->createItem(['short_name' => 'A Item']);

    $data = $this->getJson(route('setups.items.index', ['sort_by' => 'short_name', 'sort_direction' => 'asc']))
        ->assertOk()
        ->json('data');

    expect($data[0]['short_name'])->toBe('A Item')
        ->and($data[1]['short_name'])->toBe('Z Item');
});

it('paginates results', function () {
    for ($i = 0; $i < 7; $i++) {
        $this->createItem();
    }

    $response = $this->getJson(route('setups.items.index', ['per_page' => 3]))->assertOk();

    expect($response->json('data'))->toHaveCount(3)
        ->and($response->json('pagination.total'))->toBe(7)
        ->and($response->json('pagination.per_page'))->toBe(3)
        ->and($response->json('pagination.last_page'))->toBe(3);
});
