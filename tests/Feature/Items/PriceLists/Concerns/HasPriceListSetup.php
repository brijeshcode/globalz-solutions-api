<?php

namespace Tests\Feature\Items\PriceLists\Concerns;

use App\Models\Items\Item;
use App\Models\Items\PriceList;
use App\Models\Items\PriceListItem;
use App\Models\Setups\ItemFamily;
use App\Models\Setups\ItemType;
use App\Models\Setups\ItemUnit;
use App\Models\Setups\TaxCode;
use App\Models\User;

trait HasPriceListSetup
{
    protected User $admin;
    protected ItemType $itemType;
    protected ItemFamily $itemFamily;
    protected ItemUnit $itemUnit;
    protected TaxCode $taxCode;
    protected Item $item1;
    protected Item $item2;

    public function setUpPriceLists(): void
    {
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($this->admin, 'sanctum');

        $this->itemType   = ItemType::factory()->create();
        $this->itemFamily = ItemFamily::factory()->create();
        $this->itemUnit   = ItemUnit::factory()->create();
        $this->taxCode    = TaxCode::factory()->create();

        $this->item1 = Item::factory()->create([
            'code'           => 'ITEM-001',
            'description'    => 'Test Item 1',
            'base_sell'      => 100.00,
            'item_type_id'   => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id'   => $this->itemUnit->id,
        ]);

        $this->item2 = Item::factory()->create([
            'code'           => 'ITEM-002',
            'description'    => 'Test Item 2',
            'base_sell'      => 200.00,
            'item_type_id'   => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id'   => $this->itemUnit->id,
        ]);
    }

    protected function priceListPayload(array $overrides = []): array
    {
        return array_merge([
            'code'        => 'PL-' . fake()->unique()->numerify('####'),
            'description' => 'Test Price List',
            'note'        => 'Test note',
            'items'       => [
                [
                    'item_code'        => $this->item1->code,
                    'item_id'          => $this->item1->id,
                    'item_description' => $this->item1->description,
                    'sell_price'       => 120.00,
                ],
                [
                    'item_code'        => $this->item2->code,
                    'item_id'          => $this->item2->id,
                    'item_description' => $this->item2->description,
                    'sell_price'       => 220.00,
                ],
            ],
        ], $overrides);
    }

    protected function createPriceList(array $overrides = []): PriceList
    {
        return PriceList::factory()->create(array_merge([
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ], $overrides));
    }

    protected function createPriceListItem(PriceList $priceList, array $overrides = []): PriceListItem
    {
        return PriceListItem::factory()->create(array_merge([
            'price_list_id' => $priceList->id,
            'created_by'    => $this->admin->id,
            'updated_by'    => $this->admin->id,
        ], $overrides));
    }
}
