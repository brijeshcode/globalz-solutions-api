<?php

use App\Models\Suppliers\PurchaseReturn;
use Tests\Feature\Suppliers\PurchaseReturns\Concerns\HasPurchaseReturnSetup;

uses(HasPurchaseReturnSetup::class);

uses()->group('api', 'suppliers', 'purchase-returns');

beforeEach(function () {
    $this->setUpPurchaseReturns();
});

it('auto-generates purchase return code', function () {
    $this->setupInitialInventory($this->item1->id, 50, 30.00);

    $this->postJson(route('suppliers.purchase-returns.store'), $this->purchaseReturnPayload([
        'items' => [
            ['item_id' => $this->item1->id, 'price' => 30.00, 'quantity' => 5],
        ],
    ]))->assertCreated();

    $purchaseReturn = PurchaseReturn::where('supplier_id', $this->supplier->id)->first();
    expect($purchaseReturn->code)->not()->toBeNull()
        ->and($purchaseReturn->prefix)->toBe('PURTN');
});
