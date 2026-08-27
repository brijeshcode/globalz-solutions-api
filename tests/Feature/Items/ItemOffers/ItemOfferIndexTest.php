<?php

use App\Models\Items\Item;
use App\Models\Items\ItemOffer;
use Tests\Feature\Items\ItemOffers\Concerns\HasItemOfferSetup;

uses(HasItemOfferSetup::class);

beforeEach(fn () => $this->setUpItemOffers());

it('lists item offers with correct structure', function () {
    ItemOffer::factory()->count(3)->create([
        'item_id'      => $this->item->id,
        'free_item_id' => $this->freeItem->id,
    ]);

    $this->getJson(route('items.offers.index'))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => ['*' => [
                'id', 'item_id', 'free_item_id', 'date', 'validity_date',
                'minimum_quantity', 'free_quantity', 'usage_limit',
                'is_active', 'is_available', 'used_count',
            ]],
            'pagination',
        ])
        ->assertJsonCount(3, 'data');
});

it('filters by item_id', function () {
    $other = Item::factory()->create();
    ItemOffer::factory()->create(['item_id' => $this->item->id, 'free_item_id' => $this->freeItem->id]);
    ItemOffer::factory()->create(['item_id' => $other->id,      'free_item_id' => $this->freeItem->id]);

    $data = $this->getJson(route('items.offers.index', ['item_id' => $this->item->id]))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['item_id'])->toBe($this->item->id);
});

it('filters by is_active', function () {
    ItemOffer::factory()->create(['item_id' => $this->item->id, 'free_item_id' => $this->freeItem->id, 'is_active' => true]);
    ItemOffer::factory()->inactive()->create(['item_id' => $this->item->id, 'free_item_id' => $this->freeItem->id]);

    $data = $this->getJson(route('items.offers.index', ['is_active' => 'true']))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['is_active'])->toBeTrue();
});

it('filters valid offers only', function () {
    ItemOffer::factory()->create(['item_id' => $this->item->id, 'free_item_id' => $this->freeItem->id]);
    ItemOffer::factory()->expired()->create(['item_id' => $this->item->id, 'free_item_id' => $this->freeItem->id]);

    $this->getJson(route('items.offers.index', ['valid' => true]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters by validity_date range', function () {
    ItemOffer::factory()->create([
        'item_id'      => $this->item->id,
        'free_item_id' => $this->freeItem->id,
        'date'         => '2026-01-01',
        'validity_date' => '2026-03-31',
    ]);
    ItemOffer::factory()->create([
        'item_id'      => $this->item->id,
        'free_item_id' => $this->freeItem->id,
        'date'         => '2026-01-01',
        'validity_date' => '2026-09-30',
    ]);

    $this->getJson(route('items.offers.index', ['date_from' => '2026-01-01', 'date_to' => '2026-06-30']))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('paginates results', function () {
    ItemOffer::factory()->count(7)->create([
        'item_id'      => $this->item->id,
        'free_item_id' => $this->freeItem->id,
    ]);

    $response = $this->getJson(route('items.offers.index', ['per_page' => 3]))
        ->assertOk()
        ->assertJsonCount(3, 'data');

    expect($response->json('pagination.total'))->toBe(7);
});
