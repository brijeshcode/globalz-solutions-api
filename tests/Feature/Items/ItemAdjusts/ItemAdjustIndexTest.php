<?php

use App\Models\Items\ItemAdjust;
use App\Models\Setups\Warehouse;
use Tests\Feature\Items\ItemAdjusts\Concerns\HasItemAdjustSetup;

uses(HasItemAdjustSetup::class);

beforeEach(function () {
    $this->setUpItemAdjusts();
    $this->actingAsAdmin();
});

it('lists item adjusts with correct structure', function () {
    ItemAdjust::factory()->count(3)->create(['warehouse_id' => $this->warehouse->id]);

    $this->getJson(route('items.adjusts.index'))
        ->assertOk()
        ->assertJsonStructure(['message', 'data' => ['*' => ['id', 'code', 'date', 'type', 'warehouse']], 'pagination'])
        ->assertJsonCount(3, 'data');
});

it('filters by warehouse', function () {
    $other = Warehouse::factory()->create(['name' => 'Other Warehouse']);
    ItemAdjust::factory()->create(['warehouse_id' => $this->warehouse->id]);
    ItemAdjust::factory()->create(['warehouse_id' => $other->id]);

    $this->getJson(route('items.adjusts.index', ['warehouse_id' => $this->warehouse->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters by type', function () {
    ItemAdjust::factory()->add()->create(['warehouse_id' => $this->warehouse->id]);
    ItemAdjust::factory()->subtract()->create(['warehouse_id' => $this->warehouse->id]);

    $this->getJson(route('items.adjusts.index', ['type' => 'Add']))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('paginates results', function () {
    ItemAdjust::factory()->count(7)->create(['warehouse_id' => $this->warehouse->id]);

    $response = $this->getJson(route('items.adjusts.index', ['per_page' => 3]))->assertOk();

    expect($response->json('data'))->toHaveCount(3)
        ->and($response->json('pagination.total'))->toBe(7)
        ->and($response->json('pagination.per_page'))->toBe(3)
        ->and($response->json('pagination.last_page'))->toBe(3);
});
