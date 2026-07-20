<?php

use App\Models\Customers\ProformaInvoice;
use App\Models\Customers\ProformaInvoiceItem;
use Tests\Feature\Customers\ProformaInvoices\Concerns\HasProformaSetup;

uses(HasProformaSetup::class);

beforeEach(function () {
    $this->setUpProforma();
    $this->actingAs($this->admin, 'sanctum');
});

it('admin creates a proforma invoice with status Draft', function () {
    $this->postJson(route('proforma-invoices.store'), $this->proformaPayload())
        ->assertCreated()
        ->assertJsonPath('data.status', 'Draft')
        ->assertJsonPath('data.prefix', 'PINV');

    expect(ProformaInvoice::count())->toBe(1);
    expect(ProformaInvoiceItem::count())->toBe(1);
});

it('salesman can also create a proforma invoice', function () {
    $this->actingAs($this->salesman, 'sanctum');

    $this->postJson(route('proforma-invoices.store'), $this->proformaPayload())
        ->assertCreated();
});

it('rejects invalid prefix', function () {
    $this->postJson(route('proforma-invoices.store'), $this->proformaPayload(['prefix' => 'INV']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['prefix']);
});

it('does NOT touch inventory when creating', function () {
    $before = \App\Models\Inventory\Inventory::where('item_id', $this->item->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->value('quantity') ?? 0;

    $this->postJson(route('proforma-invoices.store'), $this->proformaPayload())
        ->assertCreated();

    $after = \App\Models\Inventory\Inventory::where('item_id', $this->item->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->value('quantity') ?? 0;

    expect($after)->toBe($before);
});

it('generates a PINV code', function () {
    $this->postJson(route('proforma-invoices.store'), $this->proformaPayload())
        ->assertCreated();

    $proforma = ProformaInvoice::first();
    expect($proforma->proforma_code)->toStartWith('PINV');
});

it('creates a status history entry on creation', function () {
    $this->postJson(route('proforma-invoices.store'), $this->proformaPayload())
        ->assertCreated();

    $proforma = ProformaInvoice::with('statusHistories')->first();
    expect($proforma->statusHistories)->toHaveCount(1)
        ->and($proforma->statusHistories->first()->status)->toBe('Draft');
});
