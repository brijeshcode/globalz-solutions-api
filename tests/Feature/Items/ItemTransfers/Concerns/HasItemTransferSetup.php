<?php

namespace Tests\Feature\Items\ItemTransfers\Concerns;

use App\Models\Inventory\Inventory;
use App\Models\Items\Item;
use App\Models\Items\ItemTransfer;
use App\Models\Setting;
use App\Models\Setups\Warehouse;
use App\Models\User;

trait HasItemTransferSetup
{
    protected Warehouse $fromWarehouse;
    protected Warehouse $toWarehouse;
    protected Item $item1;
    protected Item $item2;

    public function setUpItemTransfers(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]), 'sanctum');

        Setting::create([
            'group_name'  => 'item_transfers',
            'key_name'    => 'code_counter',
            'value'       => '1000',
            'data_type'   => 'number',
            'description' => 'Item transfer code counter starting from 1000',
        ]);

        $this->fromWarehouse = Warehouse::factory()->create(['name' => 'Source Warehouse']);
        $this->toWarehouse   = Warehouse::factory()->create(['name' => 'Destination Warehouse']);

        $this->item1 = Item::factory()->create(['code' => 'ITEM001', 'short_name' => 'Test Item 1']);
        $this->item2 = Item::factory()->create(['code' => 'ITEM002', 'short_name' => 'Test Item 2']);
    }

    protected function transferPayload(array $overrides = []): array
    {
        $data = array_merge([
            'date'              => '2025-01-15',
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id'   => $this->toWarehouse->id,
        ], $overrides);

        if (! isset($data['items'])) {
            $data['items'] = [['item_id' => $this->item1->id, 'quantity' => 10]];
        }

        return $data;
    }

    protected function createTransferViaApi(array $overrides = []): ItemTransfer
    {
        $response = $this->postJson(route('items.transfers.store'), $this->transferPayload($overrides));
        $response->assertCreated();

        return ItemTransfer::find($response->json('data.id'));
    }

    protected function setupInventory(int $itemId, int $warehouseId, float $quantity): void
    {
        Inventory::updateOrCreate(
            ['warehouse_id' => $warehouseId, 'item_id' => $itemId],
            ['quantity' => $quantity]
        );
    }
}
