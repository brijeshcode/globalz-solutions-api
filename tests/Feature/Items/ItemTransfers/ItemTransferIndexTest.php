<?php

use App\Models\Items\ItemTransfer;
use App\Models\Setups\Warehouse;
use Tests\Feature\Items\ItemTransfers\Concerns\HasItemTransferSetup;

uses(HasItemTransferSetup::class);

beforeEach(function () {
    $this->setUpItemTransfers();
});

it('lists item transfers with correct structure', function () {
    ItemTransfer::factory()->count(3)->create(['from_warehouse_id' => $this->fromWarehouse->id, 'to_warehouse_id' => $this->toWarehouse->id]);

    $this->getJson(route('items.transfers.index'))
        ->assertOk()
        ->assertJsonStructure(['message', 'data' => ['*' => ['id', 'code', 'date', 'from_warehouse', 'to_warehouse']], 'pagination'])
        ->assertJsonCount(3, 'data');
});

it('filters by source warehouse', function () {
    $other = Warehouse::factory()->create(['name' => 'Other Warehouse']);
    ItemTransfer::factory()->create(['from_warehouse_id' => $this->fromWarehouse->id, 'to_warehouse_id' => $this->toWarehouse->id]);
    ItemTransfer::factory()->create(['from_warehouse_id' => $other->id, 'to_warehouse_id' => $this->toWarehouse->id]);

    $this->getJson(route('items.transfers.index', ['from_warehouse_id' => $this->fromWarehouse->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters by destination warehouse', function () {
    $other = Warehouse::factory()->create(['name' => 'Other Warehouse']);
    ItemTransfer::factory()->create(['from_warehouse_id' => $this->fromWarehouse->id, 'to_warehouse_id' => $this->toWarehouse->id]);
    ItemTransfer::factory()->create(['from_warehouse_id' => $this->fromWarehouse->id, 'to_warehouse_id' => $other->id]);

    $this->getJson(route('items.transfers.index', ['to_warehouse_id' => $this->toWarehouse->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('paginates results', function () {
    ItemTransfer::factory()->count(7)->create(['from_warehouse_id' => $this->fromWarehouse->id, 'to_warehouse_id' => $this->toWarehouse->id]);

    $response = $this->getJson(route('items.transfers.index', ['per_page' => 3]))->assertOk();

    expect($response->json('data'))->toHaveCount(3)
        ->and($response->json('pagination.total'))->toBe(7)
        ->and($response->json('pagination.per_page'))->toBe(3)
        ->and($response->json('pagination.last_page'))->toBe(3);
});
