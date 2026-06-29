<?php

namespace Tests\Feature\Calculations\ItemPrice\Concerns;

use App\Models\Inventory\ItemPrice;
use App\Models\Inventory\ItemPriceHistory;
use App\Models\Items\Item;
use App\Models\Setting;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\ItemFamily;
use App\Models\Setups\ItemType;
use App\Models\Setups\ItemUnit;
use App\Models\Setups\Supplier;
use App\Models\Setups\TaxCode;
use App\Models\Setups\Warehouse;
use App\Models\Suppliers\Purchase;
use App\Models\Suppliers\PurchaseReturn;
use App\Models\User;

trait HasItemPriceSetup
{
    protected User $user;
    protected Supplier $supplier;
    protected Warehouse $warehouse;
    protected Currency $currency;
    protected ItemType $itemType;
    protected ItemFamily $itemFamily;
    protected ItemUnit $itemUnit;
    protected TaxCode $taxCode;

    public function setUpItemPrice(): void
    {
        $this->user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($this->user, 'sanctum');

        Setting::updateOrCreate(
            ['group_name' => 'items', 'key_name' => 'code_counter'],
            ['value' => '5000', 'data_type' => 'number', 'description' => 'Item code counter']
        );

        Setting::updateOrCreate(
            ['group_name' => 'purchases', 'key_name' => 'code_counter'],
            ['value' => '1000', 'data_type' => 'number', 'description' => 'Purchase code counter']
        );

        Setting::updateOrCreate(
            ['group_name' => 'purchase_returns', 'key_name' => 'code_counter'],
            ['value' => '1000', 'data_type' => 'number', 'description' => 'Purchase return code counter']
        );

        $this->warehouse  = Warehouse::factory()->create(['name' => 'Main Warehouse', 'is_default' => true, 'is_active' => true]);
        $this->supplier   = Supplier::factory()->create(['is_active' => true]);
        $this->itemType   = ItemType::factory()->create(['is_active' => true]);
        $this->itemFamily = ItemFamily::factory()->create(['is_active' => true]);
        $this->itemUnit   = ItemUnit::factory()->create(['is_active' => true]);
        $this->taxCode    = TaxCode::factory()->create(['tax_percent' => 0, 'is_active' => true]);
        $this->currency   = Currency::where('code', 'USD')->first()
            ?? Currency::factory()->create(['code' => 'USD', 'symbol' => '$', 'is_active' => true]);
    }

    // ─── Item helpers ──────────────────────────────────────────────────────────

    protected function makeItem(string $costCalculation = Item::COST_WEIGHTED_AVERAGE, float $startingPrice = 0.0, int $startingQuantity = 0): Item
    {
        return Item::factory()->create([
            'item_type_id'      => $this->itemType->id,
            'item_family_id'    => $this->itemFamily->id,
            'item_unit_id'      => $this->itemUnit->id,
            'tax_code_id'       => $this->taxCode->id,
            'starting_price'    => $startingPrice,
            'starting_quantity' => $startingQuantity,
            'cost_calculation'  => $costCalculation,
        ]);
    }

    protected function itemPayload(array $overrides = []): array
    {
        return array_merge([
            'code'              => fake()->unique()->numerify('TITEM-#####'),
            'short_name'        => fake()->words(3, true),
            'description'       => fake()->sentence(),
            'item_type_id'      => $this->itemType->id,
            'item_family_id'    => $this->itemFamily->id,
            'item_unit_id'      => $this->itemUnit->id,
            'tax_code_id'       => $this->taxCode->id,
            'base_cost'         => 0,
            'base_sell'         => 0,
            'starting_price'    => 0,
            'starting_quantity' => 0,
            'cost_calculation'  => Item::COST_WEIGHTED_AVERAGE,
            'is_active'         => true,
        ], $overrides);
    }

    // ─── Purchase helpers ──────────────────────────────────────────────────────

    protected function purchasePayload(array $overrides = []): array
    {
        return array_merge([
            'prefix'             => 'PUR',
            'date'               => now()->format('Y-m-d'),
            'supplier_id'        => $this->supplier->id,
            'warehouse_id'       => $this->warehouse->id,
            'currency_id'        => $this->currency->id,
            'currency_rate'      => 1.0,
            'discount_amount'    => 0,
            'discount_amount_usd' => 0,
        ], $overrides);
    }

    protected function createDeliveredPurchase(int $itemId, int $quantity, float $price): Purchase
    {
        $response = $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
            'items' => [['item_id' => $itemId, 'price' => $price, 'quantity' => $quantity]],
        ]));

        $response->assertCreated();
        $purchase = Purchase::find($response->json('data.id'));

        $this->patchJson(route('suppliers.purchases.changeStatus', $purchase), ['status' => 'Delivered'])
            ->assertOk();

        return $purchase->fresh();
    }

    // ─── Purchase return helpers ───────────────────────────────────────────────

    protected function purchaseReturnPayload(array $overrides = []): array
    {
        return array_merge([
            'prefix'                          => 'PR',
            'date'                            => now()->format('Y-m-d'),
            'supplier_id'                     => $this->supplier->id,
            'warehouse_id'                    => $this->warehouse->id,
            'currency_id'                     => $this->currency->id,
            'currency_rate'                   => 1.0,
            'final_total_usd'                 => 0,
            'total_usd'                       => 0,
            'shipping_fee_usd'                => 0,
            'customs_fee_usd'                 => 0,
            'other_fee_usd'                   => 0,
            'tax_usd'                         => 0,
            'shipping_fee_usd_percent'        => 0,
            'customs_fee_usd_percent'         => 0,
            'other_fee_usd_percent'           => 0,
            'tax_usd_percent'                 => 0,
            'additional_charge_amount'        => 0,
            'additional_charge_amount_usd'    => 0,
        ], $overrides);
    }

    protected function createPurchaseReturn(int $itemId, int $quantity, float $price): PurchaseReturn
    {
        $response = $this->postJson(route('suppliers.purchase-returns.store'), $this->purchaseReturnPayload([
            'items' => [['item_id' => $itemId, 'price' => $price, 'quantity' => $quantity]],
        ]));

        $response->assertCreated();
        return PurchaseReturn::find($response->json('data.id'));
    }

    // ─── Price assertion helpers ───────────────────────────────────────────────

    protected function currentPrice(int $itemId): ?float
    {
        $record = ItemPrice::where('item_id', $itemId)->first();
        return $record ? (float) $record->price_usd : null;
    }

    protected function priceHistoryCount(int $itemId): int
    {
        return ItemPriceHistory::where('item_id', $itemId)->count();
    }

    protected function activeHistoryCount(int $itemId): int
    {
        return ItemPriceHistory::where('item_id', $itemId)
            ->where('note', '!=', 'Removed by user — no longer valid')
            ->count();
    }

    protected function currentHistoryCount(int $itemId): int
    {
        return ItemPriceHistory::where('item_id', $itemId)->where('is_current', true)->count();
    }
}
