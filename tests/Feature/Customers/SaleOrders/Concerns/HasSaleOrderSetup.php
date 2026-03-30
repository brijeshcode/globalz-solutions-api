<?php

namespace Tests\Feature\Customers\SaleOrders\Concerns;

use App\Models\Customers\Customer;
use App\Models\Customers\Sale;
use App\Models\Employees\Employee;
use App\Models\Inventory\Inventory;
use App\Models\Items\Item;
use App\Models\Setting;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\Warehouse;
use App\Models\User;

trait HasSaleOrderSetup
{
    protected User $admin;
    protected User $salesman;
    protected Employee $salesmanEmployee;
    protected Customer $customer;
    protected Currency $currency;
    protected Warehouse $warehouse;
    protected Item $item;

    public function setUpSaleOrders(): void
    {
        $this->admin   = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->salesman = User::factory()->create(['role' => User::ROLE_SALESMAN]);

        $this->salesmanEmployee = Employee::factory()->create([
            'id'        => $this->salesman->id,
            'user_id'   => $this->salesman->id,
            'is_active' => true,
        ]);

        Setting::create([
            'group_name'  => 'sales',
            'key_name'    => 'code_counter',
            'value'       => '1000',
            'data_type'   => 'number',
            'description' => 'Sale code counter starting from 1000',
        ]);

        $this->customer = Customer::factory()->create([
            'salesperson_id' => $this->salesmanEmployee->id,
            'created_by'     => $this->admin->id,
            'updated_by'     => $this->admin->id,
            'is_active'      => true,
        ]);

        $this->currency  = Currency::factory()->usd()->create(['name' => 'US Dollar']);
        $this->warehouse = Warehouse::factory()->create(['name' => 'Main Warehouse', 'is_active' => true]);

        $this->item = Item::factory()->create([
            'short_name'     => 'Test Item',
            'code'           => 'ITEM001',
            'is_active'      => true,
            'starting_price' => 100.00,
            'created_by'     => $this->admin->id,
            'updated_by'     => $this->admin->id,
        ]);

        $this->item->itemPrice()->update(['price_usd' => 50.00]);

        Inventory::create([
            'item_id'      => $this->item->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity'     => 1000.00,
        ]);
    }

    protected function saleOrderPayload(array $overrides = []): array
    {
        return array_merge([
            'date'                 => '2025-01-15',
            'prefix'               => 'INV',
            'customer_id'          => $this->customer->id,
            'salesperson_id'       => $this->salesmanEmployee->id,
            'currency_id'          => $this->currency->id,
            'warehouse_id'         => $this->warehouse->id,
            'client_po_number'     => 'PO-001',
            'currency_rate'        => 1,
            'credit_limit'         => 10000.00,
            'outStanding_balance'  => 0.00,
            'sub_total'            => 1000.00,
            'sub_total_usd'        => 1000.00,
            'discount_amount'      => 50.00,
            'discount_amount_usd'  => 50.00,
            'total'                => 950.00,
            'total_usd'            => 950.00,
            'note'                 => 'Test sale note',
            'items'                => [
                [
                    'item_id'              => $this->item->id,
                    'quantity'             => 10,
                    'price'                => 100.00,
                    'ttc_price'            => 110.00,
                    'tax_percent'          => 10.0,
                    'discount_percent'     => 5.0,
                    'unit_discount_amount' => 5.00,
                    'discount_amount'      => 50.00,
                    'total_price'          => 950.00,
                    'total_price_usd'      => 950.00,
                    'note'                 => 'Item sale note',
                ],
            ],
        ], $overrides);
    }

    protected function createPendingSaleOrder(array $overrides = []): Sale
    {
        return Sale::factory()->create(array_merge([
            'customer_id'    => $this->customer->id,
            'currency_id'    => $this->currency->id,
            'warehouse_id'   => $this->warehouse->id,
            'salesperson_id' => $this->salesmanEmployee->id,
            'approved_by'    => null,
            'approved_at'    => null,
        ], $overrides));
    }

    protected function createApprovedSaleOrder(array $overrides = []): Sale
    {
        return Sale::factory()->create(array_merge([
            'customer_id'    => $this->customer->id,
            'currency_id'    => $this->currency->id,
            'warehouse_id'   => $this->warehouse->id,
            'salesperson_id' => $this->salesmanEmployee->id,
            'approved_by'    => $this->admin->id,
            'approved_at'    => now(),
        ], $overrides));
    }
}
