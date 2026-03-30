<?php

namespace Tests\Feature\Customers\Sales\Concerns;

use App\Models\Customers\Customer;
use App\Models\Customers\Sale;
use App\Models\Inventory\ItemPrice;
use App\Models\Items\Item;
use App\Models\Setting;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\Warehouse;
use App\Models\User;
use App\Services\Inventory\InventoryService;

trait HasSaleSetup
{
    protected User $admin;
    protected Warehouse $warehouse;
    protected Currency $currency;
    protected Customer $customer;
    protected Item $item1;
    protected Item $item2;

    public function setUpSales(): void
    {
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($this->admin, 'sanctum');

        Setting::create([
            'group_name'  => 'sales',
            'key_name'    => 'code_counter',
            'value'       => '1000',
            'data_type'   => 'number',
            'description' => 'Sale code counter starting from 1000',
        ]);

        $this->warehouse = Warehouse::factory()->create(['name' => 'Main Warehouse']);
        $this->currency  = Currency::factory()->eur()->create();
        $this->customer  = Customer::factory()->create(['is_active' => true]);

        $this->item1 = Item::factory()->create([
            'code'       => 'ITEM001',
            'short_name' => 'Test Item 1',
            'base_cost'  => 50.00,
            'base_sell'  => 100.00,
        ]);
        $this->item2 = Item::factory()->create([
            'code'       => 'ITEM002',
            'short_name' => 'Test Item 2',
            'base_cost'  => 75.00,
            'base_sell'  => 150.00,
        ]);

        ItemPrice::updateOrCreate(
            ['item_id' => $this->item1->id],
            ['price_usd' => 45.00, 'effective_date' => now()]
        );
        ItemPrice::updateOrCreate(
            ['item_id' => $this->item2->id],
            ['price_usd' => 70.00, 'effective_date' => now()]
        );

        InventoryService::set($this->item1->id, $this->warehouse->id, 100, 'Initial stock');
        InventoryService::set($this->item2->id, $this->warehouse->id, 50, 'Initial stock');
    }

    protected function salePayload(array $overrides = []): array
    {
        return array_merge([
            'date'          => '2025-01-15',
            'prefix'        => 'INV',
            'warehouse_id'  => $this->warehouse->id,
            'currency_id'   => $this->currency->id,
            'customer_id'   => $this->customer->id,
            'currency_rate' => 1.25,
            'sub_total'     => 200.00,
            'sub_total_usd' => 160.00,
            'total'         => 200.00,
            'total_usd'     => 160.00,
        ], $overrides);
    }

    protected function createSaleViaApi(array $overrides = []): Sale
    {
        $data = $this->salePayload($overrides);

        if (!isset($data['items'])) {
            $data['items'] = [
                [
                    'item_id'     => $this->item1->id,
                    'price'       => 100.00,
                    'quantity'    => 2,
                    'total_price' => 200.00,
                ],
            ];
        }

        $this->postJson(route('customers.sales.store'), $data)->assertCreated();

        return Sale::latest()->first();
    }

    protected function createApprovedSale(array $overrides = []): Sale
    {
        return Sale::factory()->create(array_merge([
            'warehouse_id' => $this->warehouse->id,
            'currency_id'  => $this->currency->id,
            'approved_by'  => $this->admin->id,
            'approved_at'  => now(),
        ], $overrides));
    }
}
