<?php

use App\Models\Setting;
use App\Models\Suppliers\Purchase;
use Tests\Feature\Suppliers\Purchases\Concerns\HasPurchaseSetup;

uses(HasPurchaseSetup::class);

uses()->group('api', 'suppliers', 'purchases');

beforeEach(function () {
    $this->setUpPurchases();
});

it('gets next code when counter is 1001', function () {
    Setting::set('purchases', 'code_counter', 1001, 'number');

    expect(Purchase::getNextSuggestedCode())->toBe('001001');
});

it('creates purchase with counter value and increments', function () {
    Setting::set('purchases', 'code_counter', 1005, 'number');

    $response = $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
        'items' => [['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 1]],
    ]))->assertCreated();

    expect($response->json('data.code'))->toBe('001005');
    expect(Purchase::getNextSuggestedCode())->toBe('001006');
});

it('generates sequential purchase codes', function () {
    Purchase::withTrashed()->forceDelete();

    $response1 = $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
        'items' => [['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 1]],
    ]))->assertCreated();

    $response2 = $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
        'supplier_invoice_number' => 'INV-2025-002',
        'items'                   => [['item_id' => $this->item2->id, 'price' => 200.00, 'quantity' => 1]],
    ]))->assertCreated();

    $code1    = $response1->json('data.code');
    $code2    = $response2->json('data.code');
    $expected = str_pad((int) $code1 + 1, 6, '0', STR_PAD_LEFT);

    expect($code2)->toBe($expected);
});
