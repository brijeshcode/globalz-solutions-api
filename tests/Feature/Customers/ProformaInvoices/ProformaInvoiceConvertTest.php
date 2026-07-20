<?php

use App\Models\Customers\ProformaInvoice;
use App\Models\Customers\Sale;
use App\Models\Inventory\Inventory;
use Tests\Feature\Customers\ProformaInvoices\Concerns\HasProformaSetup;

uses(HasProformaSetup::class);

beforeEach(function () {
    $this->setUpProforma();
    $this->actingAs($this->admin, 'sanctum');

    Inventory::create([
        'item_id'      => $this->item->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity'     => 1000.00,
    ]);
});

it('converts an accepted proforma to an approved sale', function () {
    $proforma = $this->createProformaWithItem(['status' => 'Accepted', 'prefix' => 'PINV']);

    $this->postJson(route('proforma-invoices.convertToSale', $proforma))
        ->assertCreated()
        ->assertJsonPath('data.prefix', 'INV');

    $proforma->refresh();
    expect($proforma->isConverted())->toBeTrue()
        ->and($proforma->status)->toBe('Converted')
        ->and($proforma->converted_sale_id)->not->toBeNull();

    $sale = Sale::find($proforma->converted_sale_id);
    expect($sale)->not->toBeNull()
        ->and($sale->isApproved())->toBeTrue()
        ->and($sale->approved_by)->toBe($this->admin->id);
});

it('maps PINX prefix to INX on the sale', function () {
    $proforma = $this->createProformaWithItem(['status' => 'Accepted', 'prefix' => 'PINX']);

    $this->postJson(route('proforma-invoices.convertToSale', $proforma))
        ->assertCreated()
        ->assertJsonPath('data.prefix', 'INX');
});

it('deducts inventory on conversion', function () {
    $proforma  = $this->createProformaWithItem(['status' => 'Accepted']);
    $qtyBefore = Inventory::where('item_id', $this->item->id)->where('warehouse_id', $this->warehouse->id)->value('quantity');

    $this->postJson(route('proforma-invoices.convertToSale', $proforma))
        ->assertCreated();

    $qtyAfter = Inventory::where('item_id', $this->item->id)->where('warehouse_id', $this->warehouse->id)->value('quantity');
    expect($qtyAfter)->toBeLessThan($qtyBefore);
});

it('rejects conversion if not Accepted status', function () {
    $proforma = $this->createProformaWithItem(['status' => 'Sent']);

    $this->postJson(route('proforma-invoices.convertToSale', $proforma))
        ->assertUnprocessable();
});

it('rejects conversion if already converted', function () {
    $proforma = $this->createProformaWithItem([
        'status'       => 'Converted',
        'converted_at' => now(),
    ]);

    $this->postJson(route('proforma-invoices.convertToSale', $proforma))
        ->assertUnprocessable();
});

it('rejects conversion by non-admin', function () {
    $this->actingAs($this->salesman, 'sanctum');
    $proforma = $this->createProformaWithItem(['status' => 'Accepted']);

    $this->postJson(route('proforma-invoices.convertToSale', $proforma))
        ->assertForbidden();
});

it('conversion is atomic — proforma stamped only if sale succeeds', function () {
    $proforma = $this->createProformaWithItem(['status' => 'Accepted']);

    $this->postJson(route('proforma-invoices.convertToSale', $proforma))
        ->assertCreated();

    expect(Sale::count())->toBe(1);
    expect($proforma->fresh()->converted_sale_id)->toBe(Sale::first()->id);
});
