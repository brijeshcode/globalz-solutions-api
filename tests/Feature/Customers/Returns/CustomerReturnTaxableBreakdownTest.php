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
