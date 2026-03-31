<?php

use App\Models\Inventory\Inventory;
use Tests\Feature\Suppliers\PurchaseReturns\Concerns\HasPurchaseReturnSetup;

uses(HasPurchaseReturnSetup::class);

uses()->group('api', 'suppliers', 'purchase-returns');

beforeEach(function () {
    $this->setUpPurchaseReturns();
});

it('updates purchase return with item sync', function () {
    $this->setupInitialInventory($this->item1->id, 100, 50.00);
    $this->setupInitialInventory($this->item2->id, 200, 75.00);

    $purchaseReturn = $this->createPurchaseReturnViaApi([
        'currency_rate'                   => 1.0,
        'supplier_purchase_return_number' => 'RET-INITIAL',
        'items'                           => [
            ['item_id' => $this->item1->id, 'quantity' => 5, 'price' => 50.00],
        ],
    ]);

    $existingItem = $purchaseReturn->purchaseReturnItems->first();

    $this->putJson(route('suppliers.purchase-returns.update', $purchaseReturn), [
        'supplier_purchase_return_number' => 'RET-UPDATED',
        'currency_rate'                   => 1.15,
        'items'                           => [
            ['id' => $existingItem->id, 'item_id' => $this->item1->id, 'price' => 55.00, 'quantity' => 8, 'note' => 'Updated item'],
            ['item_id' => $this->item2->id, 'price' => 70.00, 'quantity' => 10, 'note' => 'New item added'],
        ],
    ])->assertOk();

    $purchaseReturn->refresh();
    expect($purchaseReturn->supplier_purchase_return_number)->toBe('RET-UPDATED')
        ->and($purchaseReturn->currency_rate)->toBe('1.150000')
        ->and($purchaseReturn->purchaseReturnItems)->toHaveCount(2);

    $existingItem->refresh();
    expect($existingItem->price)->toBe('55.0000')
        ->and($existingItem->quantity)->toBe('8.0000');

    // Initial: 100, first return: -5, update to 8: additional -3 = 92
    $inventory = Inventory::where('warehouse_id', $this->warehouse->id)->where('item_id', $this->item1->id)->first();
    expect((float) $inventory->quantity)->toBe(92.0);
});
