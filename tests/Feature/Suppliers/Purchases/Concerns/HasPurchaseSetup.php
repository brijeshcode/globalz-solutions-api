<?php

namespace Tests\Feature\Suppliers\Purchases\Concerns;

use App\Models\Items\Item;
use App\Models\Setting;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\Supplier;
use App\Models\Setups\Warehouse;
use App\Models\Suppliers\Purchase;
use App\Models\User;

trait HasPurchaseSetup
{
    protected User $user;
    protected Supplier $supplier;
    protected Warehouse $warehouse;
    protected Currency $currency;
    protected Item $item1;
    protected Item $item2;

    public function setUpPurchases(): void
    {
        $this->user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($this->user, 'sanctum');

        Setting::updateOrCreate(
            ['group_name' => 'purchases', 'key_name' => 'code_counter'],
            ['value' => '1000', 'data_type' => 'number', 'description' => 'Purchase code counter starting from 1000']
        );

        $this->supplier  = Supplier::factory()->create(['name' => 'Test Supplier']);
        $this->warehouse = Warehouse::factory()->create(['name' => 'Main Warehouse']);
        $this->currency  = Currency::where('code', 'EUR')->first()
            ?? Currency::factory()->eur()->create(['is_active' => true]);

        $this->item1 = Item::where('code', 'ITEM001')->first()
            ?? Item::factory()->create([
                'cost_calculation' => Item::COST_WEIGHTED_AVERAGE,
                'code'             => 'ITEM001',
                'short_name'       => 'Test Item 1',
            ]);

        $this->item2 = Item::where('code', 'ITEM002')->first()
            ?? Item::factory()->create([
                'cost_calculation' => Item::COST_LAST_COST,
                'code'             => 'ITEM002',
                'short_name'       => 'Test Item 2',
            ]);
    }

    protected function purchasePayload(array $overrides = []): array
    {
        return array_merge([
            'prefix'                  => 'PUR',
            'date'                    => '2025-01-15',
            'supplier_id'             => $this->supplier->id,
            'warehouse_id'            => $this->warehouse->id,
            'currency_id'             => $this->currency->id,
            'supplier_invoice_number' => 'INV-2025-001',
            'currency_rate'           => 1.25,
            'final_total_usd'         => 0,
            'total_usd'               => 0,
            'shipping_fee_usd'        => 0,
            'customs_fee_usd'         => 0,
            'other_fee_usd'           => 0,
            'tax_usd'                 => 0,
            'shipping_fee_usd_percent' => 0,
            'customs_fee_usd_percent'  => 0,
            'other_fee_usd_percent'    => 0,
            'tax_usd_percent'          => 0,
            'discount_amount'          => 0,
            'discount_amount_usd'      => 0,
        ], $overrides);
    }

    protected function createPurchaseViaApi(array $overrides = []): Purchase
    {
        $data = $this->purchasePayload($overrides);

        if (!isset($data['items'])) {
            $data['items'] = [
                [
                    'item_id'  => $this->item1->id,
                    'price'    => 100.00,
                    'quantity' => 2,
                ],
            ];
        }

        $response = $this->postJson(route('suppliers.purchases.store'), $data);
        $response->assertCreated();

        $purchase = Purchase::find($response->json('data.id'));

        $this->patchJson(route('suppliers.purchases.changeStatus', $purchase), ['status' => 'Delivered'])
            ->assertOk();

        return $purchase->fresh();
    }
}
