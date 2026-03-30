<?php

use App\Models\Customers\Sale;
use App\Models\Setting;
use Tests\Feature\Customers\Sales\Concerns\HasSaleSetup;

uses(HasSaleSetup::class);

beforeEach(function () {
    $this->setUpSales();
});

it('auto-generates a 6-digit code on creation', function () {
    $this->postJson(route('customers.sales.store'), $this->salePayload([
        'items' => [['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 1, 'total_price' => 100.00]],
    ]))->assertCreated();

    $sale = Sale::where('warehouse_id', $this->warehouse->id)->first();
    expect($sale->code)->not()->toBeNull()
        ->and($sale->code)->toMatch('/^\d{6}$/');
});

it('generates the correct code for a known counter value', function () {
    Setting::set('sales', 'code_counter', 1005, 'number');

    $code = $this->postJson(route('customers.sales.store'), $this->salePayload([
        'items' => [['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 1, 'total_price' => 100.00]],
    ]))->assertCreated()->json('data.code');

    expect($code)->toBe('001005');
});

it('generates sequential codes', function () {
    Sale::withTrashed()->forceDelete();

    $code1 = $this->postJson(route('customers.sales.store'), $this->salePayload([
        'items' => [['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 1, 'total_price' => 100.00]],
    ]))->assertCreated()->json('data.code');

    $code2 = $this->postJson(route('customers.sales.store'), $this->salePayload([
        'client_po_number' => 'PO-2025-002',
        'items'            => [['item_id' => $this->item2->id, 'price' => 150.00, 'quantity' => 1, 'total_price' => 150.00]],
    ]))->assertCreated()->json('data.code');

    expect((int) $code2)->toBe((int) $code1 + 1);
});
