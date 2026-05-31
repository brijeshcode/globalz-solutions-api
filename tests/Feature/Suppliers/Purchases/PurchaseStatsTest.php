<?php

use App\Models\Suppliers\Purchase;
use Tests\Feature\Suppliers\Purchases\Concerns\HasPurchaseSetup;

uses(HasPurchaseSetup::class);

uses()->group('api', 'suppliers', 'purchases');

beforeEach(function () {
    $this->setUpPurchases();
});

it('returns correct stats structure', function () {
    $this->getJson(route('suppliers.purchases.stats'))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => ['purchase_by_status', 'total_purchase'],
        ]);
});

it('counts purchases by status', function () {
    // The creating boot event forces status='Waiting' on every factory create,
    // so status must be set via update() after creation.
    Purchase::factory()->count(3)->create([
        'supplier_id'  => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
    ]);

    Purchase::factory()->count(2)->create([
        'supplier_id'  => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
    ])->each(fn ($p) => $p->update(['status' => 'Shipped']));

    Purchase::factory()->create([
        'supplier_id'  => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
    ])->update(['status' => 'Delivered']);

    $data = $this->getJson(route('suppliers.purchases.stats'))
        ->assertOk()
        ->json('data');

    expect($data['purchase_by_status']['Waiting'])->toBe(3)
        ->and($data['purchase_by_status']['Shipped'])->toBe(2)
        ->and($data['purchase_by_status']['Delivered'])->toBe(1);
});

it('sums final_total_usd correctly', function () {
    Purchase::factory()->create([
        'supplier_id'     => $this->supplier->id,
        'warehouse_id'    => $this->warehouse->id,
        'final_total_usd' => 100.00,
    ]);
    Purchase::factory()->create([
        'supplier_id'     => $this->supplier->id,
        'warehouse_id'    => $this->warehouse->id,
        'final_total_usd' => 250.00,
    ]);

    $total = $this->getJson(route('suppliers.purchases.stats'))
        ->assertOk()
        ->json('data.total_purchase');

    expect((float) $total)->toBe(350.0);
});

it('returns zero total when there are no purchases', function () {
    $data = $this->getJson(route('suppliers.purchases.stats'))
        ->assertOk()
        ->json('data');

    expect((float) $data['total_purchase'])->toBe(0.0)
        ->and($data['purchase_by_status'])->toBeEmpty();
});

it('respects supplier_id filter in stats', function () {
    $otherSupplier = \App\Models\Setups\Supplier::factory()->create();

    Purchase::factory()->create([
        'supplier_id'     => $this->supplier->id,
        'warehouse_id'    => $this->warehouse->id,
        'final_total_usd' => 200.00,
        'status'          => 'Waiting',
    ]);
    Purchase::factory()->create([
        'supplier_id'     => $otherSupplier->id,
        'warehouse_id'    => $this->warehouse->id,
        'final_total_usd' => 500.00,
        'status'          => 'Waiting',
    ]);

    $data = $this->getJson(route('suppliers.purchases.stats', ['supplier_id' => $this->supplier->id]))
        ->assertOk()
        ->json('data');

    expect((float) $data['total_purchase'])->toBe(200.0)
        ->and($data['purchase_by_status']['Waiting'])->toBe(1);
});
