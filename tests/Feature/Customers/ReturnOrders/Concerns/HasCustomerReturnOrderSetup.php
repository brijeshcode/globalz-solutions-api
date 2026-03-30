<?php

namespace Tests\Feature\Customers\ReturnOrders\Concerns;

use App\Models\Customers\Customer;
use App\Models\Customers\CustomerReturn;
use App\Models\Employees\Employee;
use App\Models\Items\Item;
use App\Models\Setting;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\Warehouse;
use App\Models\User;

trait HasCustomerReturnOrderSetup
{
    protected User $admin;
    protected User $salesman;
    protected Customer $customer;
    protected Currency $currency;
    protected Warehouse $warehouse;
    protected Item $item;

    public function setUpCustomerReturnOrders(): void
    {
        $this->admin   = User::factory()->create(['role' => User::ROLE_ADMIN]);
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
        ]);

        $this->currency  = Currency::factory()->usd()->create(['name' => 'US Dollar']);
        $this->warehouse = Warehouse::factory()->create(['name' => 'Main Warehouse', 'is_active' => true]);
        $this->item      = Item::factory()->create([
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
            'currency_rate'    => 1,
            'total'            => 1000.00,
            'total_usd'        => 800.00,
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
                    'ttc_price'            => 11,
                    'total_price'          => 110,
                    'discount_amount'      => 0,
                    'total_price_usd'      => 150,
                    'total_volume_cbm'     => 1.5,
                    'total_weight_kg'      => 25.0,
                    'note'                 => 'Item return note',
                ],
            ],
        ], $overrides);
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
}
