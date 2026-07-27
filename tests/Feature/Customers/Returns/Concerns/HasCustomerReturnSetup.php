<?php

namespace Tests\Feature\Customers\Returns\Concerns;

use App\Models\Customers\Customer;
use App\Models\Customers\CustomerReturn;
use App\Models\Customers\Sale;
use App\Models\Customers\SaleItems;
use App\Models\Employees\Employee;
use App\Models\Items\Item;
use App\Models\Setting;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\Warehouse;
use App\Models\User;

trait HasCustomerReturnSetup
{
    protected User $superAdmin;
    protected User $admin;
    protected User $salesman;
    protected Customer $customer;
    protected Currency $currency;
    protected Warehouse $warehouse;
    protected Item $item;

    public function setUpCustomerReturns(): void
    {
        $this->superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->admin      = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->salesman = User::factory()->create(['role' => User::ROLE_SALESMAN]);

        // Employee ID matches salesman user ID so salesperson_id lookups resolve correctly
        Employee::factory()->create([
            'id'        => $this->salesman->id,
            'user_id'   => $this->salesman->id,
            'is_active' => true,
        ]);

        $this->customer = Customer::factory()->create([
            'salesperson_id' => $this->salesman->id,
            'created_by'     => $this->admin->id,
            'updated_by'     => $this->admin->id,
            'is_active'      => true,
            'address'        => '123 Test Street',
            'city'           => 'Test City',
            'mobile'         => '+1234567890',
            'mof_tax_number' => '12345678901',
        ]);

        $this->currency  = Currency::factory()->usd()->create(['name' => 'US Dollar']);
        $this->warehouse = Warehouse::factory()->create([
            'name'           => 'Main Warehouse',
            'is_active'      => true,
            'address_line_1' => '123 Warehouse Street',
        ]);
        $this->item = Item::factory()->create([
            'short_name' => 'Test Item',
            'code'       => 'ITEM001',
            'is_active'  => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        Setting::create([
            'group_name'  => 'customer_returns',
            'key_name'    => 'code_counter',
            'value'       => '999',
            'data_type'   => 'number',
            'description' => 'Customer return code counter',
        ]);
    }

    protected function returnPayload(array $overrides = []): array
    {
        return array_merge([
            'date'             => '2025-01-15',
            'prefix'           => 'RTX',
            'customer_id'      => $this->customer->id,
            'salesperson_id'   => $this->salesman->id,
            'currency_id'      => $this->currency->id,
            'warehouse_id'     => $this->warehouse->id,
            'total'            => 1000.00,
            'total_usd'        => 800.00,
            'currency_rate'    => 1,
            'total_volume_cbm' => 1.5,
            'total_weight_kg'  => 25.0,
            'note'             => 'Test return note',
            'items'            => [
                [
                    'item_code'            => 'ITEM001',
                    'item_id'              => $this->item->id,
                    'quantity'             => 10,
                    'price'                => 100.00,
                    'discount_percent'     => 5.0,
                    'unit_discount_amount' => 5.00,
                    'tax_percent'          => 10.0,
                    'total_volume_cbm'     => 1.5,
                    'total_weight_kg'      => 25.0,
                    'note'                 => 'Item return note',
                ],
            ],
        ], $overrides);
    }

    // Creates via HTTP API — always as admin, always results in an approved return
    protected function createReturnViaApi(array $overrides = []): CustomerReturn
    {
        $this->actingAs($this->admin, 'sanctum');

        $this->postJson(route('customers.returns.store'), $this->returnPayload($overrides))
            ->assertCreated();

        return CustomerReturn::latest()->first();
    }

    protected function createPendingReturn(array $overrides = []): CustomerReturn
    {
        return CustomerReturn::factory()->create(array_merge([
            'customer_id'    => $this->customer->id,
            'currency_id'    => $this->currency->id,
            'warehouse_id'   => $this->warehouse->id,
            'salesperson_id' => $this->salesman->id,
            'approved_by'    => null,
            'approved_at'    => null,
        ], $overrides));
    }

    protected function createApprovedReturn(array $overrides = []): CustomerReturn
    {
        return CustomerReturn::factory()->create(array_merge([
            'customer_id'    => $this->customer->id,
            'currency_id'    => $this->currency->id,
            'warehouse_id'   => $this->warehouse->id,
            'salesperson_id' => $this->salesman->id,
            'approved_by'    => $this->admin->id,
            'approved_at'    => now(),
        ], $overrides));
    }

    protected function createReceivedReturn(array $overrides = []): CustomerReturn
    {
        return CustomerReturn::factory()->create(array_merge([
            'customer_id'        => $this->customer->id,
            'currency_id'        => $this->currency->id,
            'warehouse_id'       => $this->warehouse->id,
            'salesperson_id'     => $this->salesman->id,
            'approved_by'        => $this->superAdmin->id,
            'approved_at'        => now(),
            'return_received_by' => $this->superAdmin->id,
            'return_received_at' => now(),
        ], $overrides));
    }

    protected function createSaleItemForCustomer(int $availableQty = 20, float $ttcPriceUsd = 10.00): SaleItems
    {
        $sale = Sale::factory()->create([
            'customer_id' => $this->customer->id,
            'created_by'  => $this->superAdmin->id,
            'updated_by'  => $this->superAdmin->id,
        ]);

        // Use withoutEvents to avoid the SaleItems::created boot hook that deducts inventory
        return SaleItems::withoutEvents(fn () => SaleItems::create([
            'sale_id'                  => $sale->id,
            'item_id'                  => $this->item->id,
            'item_code'                => $this->item->code,
            'quantity'                 => $availableQty,
            'price'                    => 100.00,
            'ttc_price'                => $ttcPriceUsd,
            'ttc_price_usd'            => $ttcPriceUsd,
            'discount_percent'         => 0,
            'unit_discount_amount'     => 0,
            'unit_discount_amount_usd' => 0,
            'tax_percent'              => 0,
            'tax_amount'               => 0,
            'tax_amount_usd'           => 0,
            'tax_label'                => 'TVA',
            'unit_profit'              => 0,
            'total_price'              => $ttcPriceUsd * $availableQty,
            'total_price_usd'          => $ttcPriceUsd * $availableQty,
            'created_by'               => $this->superAdmin->id,
            'updated_by'               => $this->superAdmin->id,
        ]));
    }
}
