<?php

use App\Models\Customers\CustomerReturn;
use Tests\Feature\Customers\Returns\Concerns\HasCustomerReturnSetup;

uses(HasCustomerReturnSetup::class);

beforeEach(function () {
    $this->setUpCustomerReturns();
});

it('populates taxable and tax breakdown on a direct return item and parent', function () {
    // Direct returns have no linked sale item, so the taxable base must be derived
    // from the item's own price/discount, with tax = total_price - taxable.
    $payload = [
        'date'           => '2025-01-15',
        'prefix'         => 'RTN',
        'customer_id'    => $this->customer->id,
        'salesperson_id' => $this->salesman->id,
        'currency_id'    => $this->currency->id,
        'warehouse_id'   => $this->warehouse->id,
        'currency_rate'  => 1,
        'total'          => 198.00,
        'total_usd'      => 198.00,
        'items'          => [
            [
                'item_id'          => $this->item->id,
                'item_code'        => 'ITEM001',
                'quantity'         => 2,
                'price'            => 100.00,   // gross unit price
                'price_usd'        => 100.00,
                'discount_percent' => 10.0,     // -> unit discount 10 -> taxable 90/unit
                'tax_percent'      => 10.0,
                'tax_amount'       => 9.00,
                'tax_amount_usd'   => 9.00,
                'ttc_price'        => 99.00,
                'ttc_price_usd'    => 99.00,
                'total_price'      => 198.00,   // tax-inclusive line total
                'total_price_usd'  => 198.00,
            ],
        ],
    ];

    $this->actingAs($this->admin, 'sanctum');

    $this->postJson(route('customers.return-orders.store-direct'), $payload)
        ->assertCreated();

    $return = CustomerReturn::latest()->first();
    $item   = $return->items()->first();

    // taxable base = price - discount = 90/unit -> 180 line; tax = total(198) - taxable(180) = 18
    expect((float) $item->unit_taxable_amount)->toBe(90.00)
        ->and((float) $item->total_taxable_amount)->toBe(180.00)
        ->and((float) $item->total_tax_amount)->toBe(18.00)
        ->and((float) $return->subtotal_taxable_amount)->toBe(180.00)
        ->and((float) $return->total_tax_amount)->toBe(18.00);
});
