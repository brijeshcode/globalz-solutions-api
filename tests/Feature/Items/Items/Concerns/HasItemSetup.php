<?php

namespace Tests\Feature\Items\Items\Concerns;

use App\Models\Items\Item;
use App\Models\Setting;
use App\Models\Setups\ItemBrand;
use App\Models\Setups\ItemCategory;
use App\Models\Setups\ItemFamily;
use App\Models\Setups\ItemGroup;
use App\Models\Setups\ItemType;
use App\Models\Setups\ItemUnit;
use App\Models\Setups\Supplier;
use App\Models\Setups\TaxCode;
use App\Models\User;

trait HasItemSetup
{
    protected User $admin;
    protected ItemType $itemType;
    protected ItemFamily $itemFamily;
    protected ItemGroup $itemGroup;
    protected ItemCategory $itemCategory;
    protected ItemBrand $itemBrand;
    protected ItemUnit $itemUnit;
    protected Supplier $supplier;
    protected TaxCode $taxCode;

    public function setUpItems(): void
    {
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($this->admin, 'sanctum');

        Setting::create([
            'group_name'  => 'items',
            'key_name'    => 'code_counter',
            'value'       => '5000',
            'data_type'   => 'number',
            'description' => 'Item code counter',
        ]);

        $this->itemType     = ItemType::factory()->create();
        $this->itemFamily   = ItemFamily::factory()->create();
        $this->itemGroup    = ItemGroup::factory()->create();
        $this->itemCategory = ItemCategory::factory()->create();
        $this->itemBrand    = ItemBrand::factory()->create();
        $this->itemUnit     = ItemUnit::factory()->create();
        $this->supplier     = Supplier::factory()->create();
        $this->taxCode      = TaxCode::factory()->create();
    }

    protected function itemPayload(array $overrides = []): array
    {
        return array_merge([
            'short_name'     => 'Test Item',
            'description'    => 'Test item description',
            'item_type_id'   => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id'   => $this->itemUnit->id,
            'tax_code_id'    => $this->taxCode->id,
            'base_cost'      => 100.00,
            'base_sell'      => 120.00,
        ], $overrides);
    }

    protected function createItem(array $overrides = []): Item
    {
        return Item::factory()->create(array_merge([
            'item_type_id'   => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id'   => $this->itemUnit->id,
        ], $overrides));
    }
}
