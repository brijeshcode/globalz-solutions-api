<?php

use Tests\Feature\Customers\Returns\Concerns\HasCustomerReturnSetup;

uses(HasCustomerReturnSetup::class);

beforeEach(function () {
    $this->setUpCustomerReturns();
});

it('stores taxable amount and tax breakdown on the return item for a taxable (RTN) return', function () {
    $saleItem = $this->createSaleItemWithBreakdown();

    $return = $this->createReturnViaApi([
        'prefix' => 'RTN',
        'items'  => [
            ['sale_item_id' => $saleItem->id, 'quantity' => 2],
        ],
    ]);

    $item = $return->items()->first();

    // unit taxable base = net sell price (after discount, before tax)
    expect((float) $item->unit_taxable_amount)->toBe(95.00)
        ->and((float) $item->unit_taxable_amount_usd)->toBe(95.00)
        // total taxable = unit taxable * qty
        ->and((float) $item->total_taxable_amount)->toBe(190.00)
        ->and((float) $item->total_taxable_amount_usd)->toBe(190.00)
        // total tax = total_price - total_taxable = (104.5*2) - 190 = 19
        ->and((float) $item->total_tax_amount)->toBe(19.00)
        ->and((float) $item->total_tax_amount_usd)->toBe(19.00);
});

it('aggregates subtotal and tax onto the parent return for a taxable (RTN) return', function () {
    $saleItem = $this->createSaleItemWithBreakdown();

    $return = $this->createReturnViaApi([
        'prefix' => 'RTN',
        'items'  => [
            ['sale_item_id' => $saleItem->id, 'quantity' => 2],
        ],
    ]);

    expect((float) $return->subtotal_taxable_amount)->toBe(190.00)
        ->and((float) $return->subtotal_taxable_amount_usd)->toBe(190.00)
        ->and((float) $return->total_tax_amount)->toBe(19.00)
        ->and((float) $return->total_tax_amount_usd)->toBe(19.00);
});

it('computes the taxable total from full-precision price/discount, not a rounded net_sell_price', function () {
    // Reproduces a legacy sale item whose per-unit net_sell_price was stored rounded to 2 dp
    // (0.58) while price (0.73) and unit_discount_amount (0.146) kept full precision.
    // The return must not multiply the rounded 0.58 by qty (=> 13.92); it must use
    // 0.73 - 0.146 = 0.584 => 0.584 * 24 = 14.016 -> 14.02.
    $sale = App\Models\Customers\Sale::factory()->create([
        'customer_id' => $this->customer->id,
        'created_by'  => $this->superAdmin->id,
        'updated_by'  => $this->superAdmin->id,
    ]);

    $saleItem = App\Models\Customers\SaleItems::withoutEvents(fn () => App\Models\Customers\SaleItems::create([
        'sale_id'                  => $sale->id,
        'item_id'                  => $this->item->id,
        'item_code'                => $this->item->code,
        'quantity'                 => 24,
        'price'                    => 0.73,
        'price_usd'                => 0.73,
        'discount_percent'         => 20.0,
        'unit_discount_amount'     => 0.146,
        'unit_discount_amount_usd' => 0.146,
        'net_sell_price'           => 0.58, // rounded (the bug source)
        'net_sell_price_usd'       => 0.58,
        'tax_percent'              => 0,
        'tax_amount'               => 0,
        'tax_amount_usd'           => 0,
        'tax_label'                => 'No',
        'ttc_price'                => 0.584,
        'ttc_price_usd'            => 0.584,
        'unit_profit'              => 0,
        'total_price'              => 0.584 * 24,
        'total_price_usd'          => 0.584 * 24,
        'created_by'               => $this->superAdmin->id,
        'updated_by'               => $this->superAdmin->id,
    ]));

    $return = $this->createReturnViaApi([
        'prefix' => 'RTN',
        'items'  => [
            ['sale_item_id' => $saleItem->id, 'quantity' => 24],
        ],
    ]);

    $item = $return->items()->first();

    expect((float) $item->total_taxable_amount)->toBe(14.02)
        ->and((float) $item->total_taxable_amount_usd)->toBe(14.02)
        ->and((float) $return->subtotal_taxable_amount)->toBe(14.02);
});

it('records zero tax for a tax-free (RTX) return, with taxable amount equal to total', function () {
    $saleItem = $this->createSaleItemWithBreakdown();

    $return = $this->createReturnViaApi([
        'prefix' => 'RTX',
        'items'  => [
            ['sale_item_id' => $saleItem->id, 'quantity' => 2],
        ],
    ]);

    $item = $return->items()->first();

    // RTX price = price * (1 - disc%) = 95 per unit -> total 190, no tax
    expect((float) $item->total_taxable_amount)->toBe(190.00)
        ->and((float) $item->total_tax_amount)->toBe(0.00)
        ->and((float) $return->subtotal_taxable_amount)->toBe(190.00)
        ->and((float) $return->total_tax_amount)->toBe(0.00);
});

it('zeroes the per-unit tax fields on a tax-free (RTX) return, mirroring INX sales', function () {
    // Sale item was taxed (10%): tax_amount 9.5, ttc 104.5, label TVA
    $saleItem = $this->createSaleItemWithBreakdown();

    $return = $this->createReturnViaApi([
        'prefix' => 'RTX',
        'items'  => [
            ['sale_item_id' => $saleItem->id, 'quantity' => 2],
        ],
    ]);

    $item = $return->items()->first();

    // Tax is stripped on a tax-free return, exactly like an INX sale
    expect((float) $item->tax_percent)->toBe(0.00)
        ->and((float) $item->tax_amount)->toBe(0.00)
        ->and((float) $item->tax_amount_usd)->toBe(0.00)
        ->and($item->tax_label)->toBe('')
        // ttc collapses to the taxable base (no tax added)
        ->and((float) $item->ttc_price)->toBe(95.00)
        ->and((float) $item->ttc_price_usd)->toBe(95.00);
});
