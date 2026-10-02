<?php

use App\Models\Customers\Sale;
use App\Models\Customers\SaleItems;
use App\Models\Customers\SaleService;

uses(Tests\TestCase::class);

function gstSale(array $items, array $services = []): Sale
{
    $sale = new Sale();
    $sale->setRelation('items', collect(array_map(
        fn ($row) => (new SaleItems())->forceFill($row),
        $items
    )));
    $sale->setRelation('saleServices', collect(array_map(
        fn ($row) => (new SaleService())->forceFill($row),
        $services
    )));

    return $sale;
}

it('groups taxed lines by rate ascending and splits each rate in half', function () {
    $sale = gstSale(
        items: [
            ['tax_percent' => 18, 'total_tax_amount' => 180, 'total_tax_amount_usd' => 180],
            ['tax_percent' => 5,  'total_tax_amount' => 100, 'total_tax_amount_usd' => 100],
            ['tax_percent' => 0,  'total_tax_amount' => 0,   'total_tax_amount_usd' => 0],
        ],
        services: [
            ['tax_percent' => 5, 'total_tax_amount' => 50, 'total_tax_amount_usd' => 50],
        ],
    );

    $summary = $sale->gstRateSummary(true);

    expect($summary)->toHaveCount(2);

    // Lowest rate first
    expect($summary[0]['percent'])->toBe(5.0);
    expect($summary[0]['half_percent'])->toBe(2.5);
    expect($summary[0]['tax'])->toBe(150.0);   // 100 item + 50 service
    expect($summary[0]['half'])->toBe(75.0);   // CGST = SGST = tax / 2

    expect($summary[1]['percent'])->toBe(18.0);
    expect($summary[1]['half_percent'])->toBe(9.0);
    expect($summary[1]['tax'])->toBe(180.0);
    expect($summary[1]['half'])->toBe(90.0);
});

it('excludes services when includeServices is false', function () {
    $sale = gstSale(
        items: [['tax_percent' => 5, 'total_tax_amount' => 100, 'total_tax_amount_usd' => 100]],
        services: [['tax_percent' => 5, 'total_tax_amount' => 50, 'total_tax_amount_usd' => 50]],
    );

    $summary = $sale->gstRateSummary(false);

    expect($summary)->toHaveCount(1);
    expect($summary[0]['tax'])->toBe(100.0);
});

it('excludes zero-rate lines entirely', function () {
    $sale = gstSale(items: [['tax_percent' => 0, 'total_tax_amount' => 0, 'total_tax_amount_usd' => 0]]);

    expect($sale->gstRateSummary(false))->toBe([]);
});
