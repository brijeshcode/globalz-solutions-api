<?php

use App\Models\Inventory\Inventory;
use App\Models\Suppliers\PurchaseReturn;
use Tests\Feature\Suppliers\PurchaseReturns\Concerns\HasPurchaseReturnSetup;

uses(HasPurchaseReturnSetup::class);

uses()->group('api', 'suppliers', 'purchase-returns');

beforeEach(function () {
    $this->setUpPurchaseReturns();
});

it('creates purchase return with items and reduces inventory', function () {
    $this->setupInitialInventory($this->item1->id, 100, 50.00);
    $this->setupInitialInventory($this->item2->id, 200, 75.00);

    $data = $this->purchaseReturnPayload([
        'shipping_fee_usd' => 25.00,
        'customs_fee_usd'  => 10.00,
        'note'             => 'Test purchase return with multiple items',
        'items'            => [
            [
                'item_id'          => $this->item1->id,
                'price'            => 50.00,
                'quantity'         => 10,
                'discount_percent' => 5,
                'note'             => 'First returned item',
            ],
            [
                'item_id'              => $this->item2->id,
                'price'                => 75.00,
                'quantity'             => 15,
                'unit_discount_amount' => 5.00,
                'note'                 => 'Second returned item',
            ],
        ],
    ]);

    $this->postJson(route('suppliers.purchase-returns.store'), $data)
        ->assertCreated()
        ->assertJsonStructure([
            'message',
            'data' => ['id', 'code', 'items', 'supplier', 'warehouse'],
        ]);

    $this->assertDatabaseHas('purchase_returns', [
        'supplier_id'                    => $this->supplier->id,
        'warehouse_id'                   => $this->warehouse->id,
        'supplier_purchase_return_number' => 'RET-2025-001',
    ]);

    $purchaseReturn = PurchaseReturn::latest()->first();
    expect($purchaseReturn->purchaseReturnItems)->toHaveCount(2);

    $inventory1 = Inventory::where('warehouse_id', $this->warehouse->id)->where('item_id', $this->item1->id)->first();
    expect((float) $inventory1->quantity)->toBe(90.0); // 100 - 10

    $inventory2 = Inventory::where('warehouse_id', $this->warehouse->id)->where('item_id', $this->item2->id)->first();
    expect((float) $inventory2->quantity)->toBe(185.0); // 200 - 15
});

it('validates required fields', function () {
    $this->postJson(route('suppliers.purchase-returns.store'), [
        'supplier_id'  => 999,
        'warehouse_id' => null,
        'currency_id'  => null,
        'items'        => [
            ['item_id' => 999, 'price' => -10, 'quantity' => 0],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'supplier_id',
            'items.0.item_id',
            'items.0.quantity',
        ]);
});

it('calculates purchase return totals correctly', function () {
    $this->setupInitialInventory($this->item1->id, 100, 50.00);

    $this->postJson(route('suppliers.purchase-returns.store'), $this->purchaseReturnPayload([
        'currency_rate'                => 0.5,
        'shipping_fee_usd'             => 20.00,
        'customs_fee_usd'              => 10.00,
        'additional_charge_amount_usd' => 5.00,
        'items'                        => [
            [
                'item_id'          => $this->item1->id,
                'price'            => 100.00,
                'quantity'         => 2,
                'discount_percent' => 10,
            ],
        ],
    ]))->assertCreated();

    $purchaseReturn = PurchaseReturn::latest()->first();

    // Item total: (100 - 10) * 2 = 180.00; USD: 180 * 0.5 = 90.00; final: 90 + 5 + 20 + 10 = 125
    expect($purchaseReturn->sub_total)->toBe('180.0000')
        ->and($purchaseReturn->sub_total_usd)->toBe('90.0000')
        ->and($purchaseReturn->final_total_usd)->toBe('125.0000');
});

it('sets created_by and updated_by automatically', function () {
    $purchaseReturn = PurchaseReturn::factory()->create([
        'supplier_id'  => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'currency_id'  => $this->currency->id,
    ]);

    expect($purchaseReturn->created_by)->toBe($this->user->id)
        ->and($purchaseReturn->updated_by)->toBe($this->user->id);

    $purchaseReturn->update(['note' => 'Updated note']);
    expect($purchaseReturn->fresh()->updated_by)->toBe($this->user->id);
});
