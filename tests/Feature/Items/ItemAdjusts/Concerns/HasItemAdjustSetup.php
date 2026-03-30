<?php

namespace Tests\Feature\Items\ItemAdjusts\Concerns;

use App\Models\Inventory\Inventory;
use App\Models\Items\Item;
use App\Models\Items\ItemAdjust;
use App\Models\Setting;
use App\Models\Setups\Warehouse;
use App\Models\User;

trait HasItemAdjustSetup
{
    protected Warehouse $warehouse;
    protected Item $item1;
    protected Item $item2;

    public function setUpItemAdjusts(): void
    {
        Setting::create([
            'group_name'  => 'item_adjusts',
            'key_name'    => 'code_counter',
            'value'       => '1000',
            'data_type'   => 'number',
            'description' => 'Item adjust code counter starting from 1000',
        ]);

        $this->warehouse = Warehouse::factory()->create(['name' => 'Test Warehouse']);

        $this->item1 = Item::factory()->create(['code' => 'ITEM001', 'short_name' => 'Test Item 1']);
        $this->item2 = Item::factory()->create(['code' => 'ITEM002', 'short_name' => 'Test Item 2']);
    }

    protected function actingAsAdmin(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]), 'sanctum');
    }

    protected function adjustPayload(array $overrides = []): array
    {
        $data = array_merge([
            'date'         => '2025-01-15',
            'type'         => 'Add',
            'warehouse_id' => $this->warehouse->id,
        ], $overrides);

        if (! isset($data['items'])) {
            $data['items'] = [['item_id' => $this->item1->id, 'quantity' => 10]];
        }

        return $data;
    }

    protected function createAdjustViaApi(array $overrides = []): ItemAdjust
    {
        $response = $this->postJson(route('items.adjusts.store'), $this->adjustPayload($overrides));
        $response->assertCreated();

        return ItemAdjust::find($response->json('data.id'));
    }

    protected function setupInventory(int $itemId, float $quantity): void
    {
        Inventory::updateOrCreate(
            ['warehouse_id' => $this->warehouse->id, 'item_id' => $itemId],
            ['quantity' => $quantity]
        );
    }
}
