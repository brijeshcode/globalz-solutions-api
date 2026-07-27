<?php

use App\Models\Customers\CustomerReturn;
use App\Models\Customers\CustomerReturnItem;
use App\Models\Inventory\Inventory;
use Tests\Feature\Customers\Returns\Concerns\HasCustomerReturnSetup;

uses(HasCustomerReturnSetup::class);

beforeEach(function () {
    $this->setUpCustomerReturns();
});

// --- Access control ---

it('admin can update date and note on a non-received return', function () {
    $return = $this->createApprovedReturn(['date' => '2025-01-01', 'note' => 'old note']);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson(route('customers.returns.update', $return), [
            'date' => '2025-06-01',
            'note' => 'corrected note',
        ])
        ->assertOk()
        ->assertJson(['data' => ['note' => 'corrected note']]);

    $this->assertDatabaseHas('customer_returns', [
        'id'   => $return->id,
        'note' => 'corrected note',
    ]);
});

it('admin cannot update a received return', function () {
    $return = $this->createReceivedReturn();

    $this->actingAs($this->admin, 'sanctum')
        ->putJson(route('customers.returns.update', $return), ['note' => 'attempt'])
        ->assertForbidden()
        ->assertJson(['message' => 'Only super admins can update received returns']);
});

it('salesman cannot update customer returns', function () {
    $return = $this->createApprovedReturn();

    $this->actingAs($this->salesman, 'sanctum')
        ->putJson(route('customers.returns.update', $return), [])
        ->assertForbidden();
});

// --- Non-received return ---

it('super admin can update a non-received return', function () {
    $return    = $this->createApprovedReturn(['note' => 'old note', 'prefix' => 'RTN']);
    $saleItem  = $this->createSaleItemForCustomer(20, 10.00);
    $returnItem = CustomerReturnItem::factory()->create([
        'customer_return_id'   => $return->id,
        'item_id'              => $this->item->id,
        'sale_item_id'         => $saleItem->id,
        'quantity'             => 5,
        'unit_discount_amount' => 0,
        'total_price_usd'      => 50.00,
    ]);

    $this->actingAs($this->superAdmin, 'sanctum')
        ->putJson(route('customers.returns.update', $return), [
            'note'  => 'updated note',
            'items' => [
                ['id' => $returnItem->id, 'sale_item_id' => $saleItem->id, 'quantity' => 5],
            ],
        ])
        ->assertOk()
        ->assertJson(['data' => ['note' => 'updated note']]);
});

// --- Inventory adjustment on received return ---

it('updating quantity on a received return adjusts warehouse inventory', function () {
    $saleItem = $this->createSaleItemForCustomer(20, 10.00);

    $return = $this->createReceivedReturn(['prefix' => 'RTN', 'total_usd' => 100.00]);

    $returnItem = CustomerReturnItem::factory()->create([
        'customer_return_id'   => $return->id,
        'item_id'              => $this->item->id,
        'sale_item_id'         => $saleItem->id,
        'quantity'             => 10,
        'unit_discount_amount' => 0,
        'total_price_usd'      => 100.00,
    ]);

    // Simulate inventory state after markAsReceived (10 units added)
    Inventory::create([
        'item_id'      => $this->item->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity'     => 10,
    ]);

    $this->actingAs($this->superAdmin, 'sanctum')
        ->putJson(route('customers.returns.update', $return), [
            'items' => [
                ['id' => $returnItem->id, 'sale_item_id' => $saleItem->id, 'quantity' => 5],
            ],
        ])
        ->assertOk();

    // Old 10 subtracted, new 5 added → net 5
    $this->assertDatabaseHas('inventories', [
        'item_id'      => $this->item->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity'     => 5,
    ]);
});

it('removing an item line from a received return removes it from inventory', function () {
    $saleItem  = $this->createSaleItemForCustomer(20, 10.00);
    $saleItem2 = $this->createSaleItemForCustomer(20, 10.00);

    $return = $this->createReceivedReturn(['prefix' => 'RTN', 'total_usd' => 150.00]);

    $item1 = CustomerReturnItem::factory()->create([
        'customer_return_id'   => $return->id,
        'item_id'              => $this->item->id,
        'sale_item_id'         => $saleItem->id,
        'quantity'             => 10,
        'unit_discount_amount' => 0,
        'total_price_usd'      => 100.00,
    ]);
    CustomerReturnItem::factory()->create([
        'customer_return_id'   => $return->id,
        'item_id'              => $this->item->id,
        'sale_item_id'         => $saleItem2->id,
        'quantity'             => 5,
        'unit_discount_amount' => 0,
        'total_price_usd'      => 50.00,
    ]);

    // Inventory reflects both items: 15 total
    Inventory::create([
        'item_id'      => $this->item->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity'     => 15,
    ]);

    // Update keeping only item1 with qty 10 (item2 line removed)
    $this->actingAs($this->superAdmin, 'sanctum')
        ->putJson(route('customers.returns.update', $return), [
            'items' => [
                ['id' => $item1->id, 'sale_item_id' => $saleItem->id, 'quantity' => 10],
            ],
        ])
        ->assertOk();

    // Old 15 subtracted, new 10 added → net 10
    $this->assertDatabaseHas('inventories', [
        'item_id'      => $this->item->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity'     => 10,
    ]);
});

// --- Customer balance adjustment on received return ---

it('updating a received return adjusts customer balance', function () {
    $saleItem = $this->createSaleItemForCustomer(20, 10.00);

    // Customer starts with a balance that includes this return's credit
    $this->customer->update(['current_balance' => 500.00]);

    $return = $this->createReceivedReturn(['prefix' => 'RTN', 'total_usd' => 100.00]);

    $returnItem = CustomerReturnItem::factory()->create([
        'customer_return_id'   => $return->id,
        'item_id'              => $this->item->id,
        'sale_item_id'         => $saleItem->id,
        'quantity'             => 10,
        'unit_discount_amount' => 0,
        'total_price_usd'      => 100.00,
    ]);

    Inventory::create([
        'item_id'      => $this->item->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity'     => 10,
    ]);

    // Reduce quantity from 10 → 5; new total_usd = 10 * 5 = 50
    $this->actingAs($this->superAdmin, 'sanctum')
        ->putJson(route('customers.returns.update', $return), [
            'items' => [
                ['id' => $returnItem->id, 'sale_item_id' => $saleItem->id, 'quantity' => 5],
            ],
        ])
        ->assertOk();

    // 500 - 100 (old) + 50 (new) = 450
    $this->assertDatabaseHas('customers', [
        'id'              => $this->customer->id,
        'current_balance' => 450.00,
    ]);
});
