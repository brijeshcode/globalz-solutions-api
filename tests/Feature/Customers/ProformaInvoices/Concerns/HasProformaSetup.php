<?php

namespace Tests\Feature\Customers\ProformaInvoices\Concerns;

use App\Models\Customers\Customer;
use App\Models\Customers\ProformaInvoice;
use App\Models\Customers\ProformaInvoiceItem;
use App\Models\Employees\Employee;
use App\Models\Items\Item;
use App\Models\Setting;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\Warehouse;
use App\Models\User;

trait HasProformaSetup
{
    protected User $admin;
    protected User $salesman;
    protected Employee $salesmanEmployee;
    protected Customer $customer;
    protected Currency $currency;
    protected Warehouse $warehouse;
    protected Item $item;

    public function setUpProforma(): void
    {
        $this->admin   = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->salesman = User::factory()->create(['role' => User::ROLE_SALESMAN]);

        $this->salesmanEmployee = Employee::factory()->create([
            'id'        => $this->salesman->id,
            'user_id'   => $this->salesman->id,
            'is_active' => true,
        ]);

        Setting::create([
            'group_name'  => 'proforma_invoices',
            'key_name'    => 'code_counter',
            'value'       => '1000',
            'data_type'   => 'number',
            'description' => 'Proforma invoice code counter',
        ]);

        Setting::create([
            'group_name'  => 'sales',
            'key_name'    => 'code_counter',
            'value'       => '2000',
            'data_type'   => 'number',
            'description' => 'Sale code counter',
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
            'short_name' => 'Test Item',
            'code'       => 'ITEM001',
            'is_active'  => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->item->itemPrice()->update(['price_usd' => 50.00]);
    }

    protected function proformaPayload(array $overrides = []): array
    {
        return array_merge([
            'date'                => '2026-07-20',
            'prefix'              => 'PINV',
            'customer_id'         => $this->customer->id,
            'salesperson_id'      => $this->salesmanEmployee->id,
            'currency_id'         => $this->currency->id,
            'warehouse_id'        => $this->warehouse->id,
            'currency_rate'       => 1,
            'sub_total'           => 900.00,
            'sub_total_usd'       => 900.00,
            'discount_amount'     => 0.00,
            'discount_amount_usd' => 0.00,
            'total'               => 900.00,
            'total_usd'           => 900.00,
            'note'                => 'Test proforma',
            'items'               => [
                [
                    'item_id'              => $this->item->id,
                    'quantity'             => 10,
                    'price'                => 100.00,
                    'ttc_price'            => 110.00,
                    'tax_percent'          => 10.0,
                    'discount_percent'     => 0.0,
                    'unit_discount_amount' => 0.00,
                    'discount_amount'      => 0.00,
                    'total_price'          => 1000.00,
                    'total_price_usd'      => 1000.00,
                ],
            ],
        ], $overrides);
    }

    protected function createProforma(array $overrides = []): ProformaInvoice
    {
        return ProformaInvoice::factory()->create(array_merge([
            'customer_id'  => $this->customer->id,
            'currency_id'  => $this->currency->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by'   => $this->admin->id,
            'updated_by'   => $this->admin->id,
        ], $overrides));
    }

    protected function createProformaWithItem(array $overrides = []): ProformaInvoice
    {
        $proforma = $this->createProforma($overrides);
        ProformaInvoiceItem::factory()->create([
            'proforma_invoice_id' => $proforma->id,
            'item_id'             => $this->item->id,
            'item_code'           => $this->item->code,
            'quantity'            => 10,
            'price'               => 100.00,
            'price_usd'           => 100.00,
            'total_price'         => 1000.00,
            'total_price_usd'     => 1000.00,
            'created_by'          => $this->admin->id,
            'updated_by'          => $this->admin->id,
        ]);
        return $proforma;
    }
}
