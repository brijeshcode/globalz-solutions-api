<?php

use App\Models\Customers\CustomerReturnItem;
use Tests\Feature\Customers\Returns\Concerns\HasCustomerReturnSetup;

uses(HasCustomerReturnSetup::class);

beforeEach(function () {
    $this->setUpCustomerReturns();
});

it('re-pulls stale return-item values from the sale and recomputes the header', function () {
    $return   = $this->createApprovedReturn(['prefix' => 'RTN']);
    $saleItem = $this->createSaleItemWithBreakdown(20); // net 95, tax 9.5, ttc 104.5 per unit

    // Return line saved with stale/wrong values; quantity 4 is the user's choice.
    $returnItem = CustomerReturnItem::factory()->create([
        'customer_return_id'   => $return->id,
        'item_id'              => $this->item->id,
        'sale_item_id'         => $saleItem->id,
        'quantity'             => 4,
        'ttc_price'            => 99.99,   // stale
        'total_taxable_amount' => 1,       // stale
        'total_tax_amount'     => 1,       // stale
        'total_price'          => 1,       // stale
        'total_price_usd'      => 1,       // stale
    ]);

    $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson(route('customers.returns.refresh', $return))
        ->assertOk()
        ->assertJsonPath('message', 'Customer return refreshed successfully')
        ->assertJsonPath('changes.has_changes', true)
        ->assertJsonPath('changes.items.0.item_code', $this->item->code)
        ->assertJsonPath('changes.items.0.fields.ttc_price.old', 99.99)
        ->assertJsonPath('changes.items.0.fields.ttc_price.new', 104.5)
        ->assertJsonPath('changes.items.0.fields.total_price.new', 418)
        ->assertJsonPath('changes.totals.total.new', 418);

    $returnItem->refresh();
    expect((int) $returnItem->quantity)->toBe(4)                     // preserved, not reset to sale qty (20)
        ->and((float) $returnItem->ttc_price)->toBe(104.5)
        ->and((float) $returnItem->total_taxable_amount)->toBe(380.0) // 95 * 4
        ->and((float) $returnItem->total_tax_amount)->toBe(38.0)      // 9.5 * 4
        ->and((float) $returnItem->total_price)->toBe(418.0);         // 104.5 * 4

    $return->refresh();
    expect((float) $return->total)->toBe(418.0)
        ->and((float) $return->subtotal_taxable_amount)->toBe(380.0)
        ->and((float) $return->total_tax_amount)->toBe(38.0);
});

it('leaves direct (non-sale-linked) lines untouched', function () {
    $return   = $this->createApprovedReturn(['prefix' => 'RTN']);
    $saleItem = $this->createSaleItemWithBreakdown(20);

    $linked = CustomerReturnItem::factory()->create([
        'customer_return_id' => $return->id,
        'item_id'            => $this->item->id,
        'sale_item_id'       => $saleItem->id,
        'quantity'           => 2,
        'total_price'        => 1, // stale
    ]);

    $direct = CustomerReturnItem::factory()->create([
        'customer_return_id' => $return->id,
        'item_id'            => $this->item->id,
        'sale_item_id'       => null,
        'quantity'           => 3,
        'ttc_price'          => 7.00,
        'total_price'        => 21.00,
    ]);

    $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson(route('customers.returns.refresh', $return))
        ->assertOk();

    expect((float) $linked->refresh()->total_price)->toBe(209.0);  // 104.5 * 2, refreshed
    expect((float) $direct->refresh()->total_price)->toBe(21.0);   // untouched
});

it('adjusts customer balance when refreshing a received return', function () {
    $saleItem = $this->createSaleItemWithBreakdown(20);

    // Balance already includes this return's old credit of 100.
    $this->customer->update(['current_balance' => 500.00]);
    $return = $this->createReceivedReturn(['prefix' => 'RTN', 'total_usd' => 100.00]);

    CustomerReturnItem::factory()->create([
        'customer_return_id' => $return->id,
        'item_id'            => $this->item->id,
        'sale_item_id'       => $saleItem->id,
        'quantity'           => 4,
        'total_price_usd'    => 1, // stale -> refresh recomputes to 418
    ]);

    $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson(route('customers.returns.refresh', $return))
        ->assertOk();

    // 500 - 100 (old) + 418 (new) = 818
    expect((float) $this->customer->refresh()->current_balance)->toBe(818.0);
});

it('reports no changes needed when the return already matches the sale', function () {
    $return   = $this->createApprovedReturn(['prefix' => 'RTN']);
    $saleItem = $this->createSaleItemWithBreakdown(20);

    CustomerReturnItem::factory()->create([
        'customer_return_id' => $return->id,
        'item_id'            => $this->item->id,
        'sale_item_id'       => $saleItem->id,
        'quantity'           => 4,
        'ttc_price'          => 1, // stale
    ]);

    // First refresh normalises every field to match the sale.
    $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson(route('customers.returns.refresh', $return))
        ->assertOk()
        ->assertJsonPath('changes.has_changes', true);

    // Second refresh finds nothing left to change.
    $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson(route('customers.returns.refresh', $return))
        ->assertOk()
        ->assertJsonPath('changes.has_changes', false)
        ->assertJsonPath('changes.items', [])
        ->assertJsonPath('changes.totals', [])
        ->assertJsonPath('message', 'No changes needed — this return already matches the sale.');
});

it('rejects refreshing a direct return that has no linked sale', function () {
    $return = $this->createApprovedReturn(['prefix' => 'RTN', 'total' => 50.00]);

    $directItem = CustomerReturnItem::factory()->create([
        'customer_return_id' => $return->id,
        'item_id'            => $this->item->id,
        'sale_item_id'       => null, // direct return line
        'quantity'           => 5,
        'ttc_price'          => 10.00,
        'total_price'        => 50.00,
    ]);

    $this->actingAs($this->superAdmin, 'sanctum')
        ->postJson(route('customers.returns.refresh', $return))
        ->assertStatus(422)
        ->assertJson(['message' => 'This is a direct return with no linked sale to refresh from.']);

    // Nothing touched.
    expect((float) $directItem->refresh()->total_price)->toBe(50.0);
    expect((float) $return->refresh()->total)->toBe(50.0);
});

it('forbids a non-super-admin from refreshing a received return', function () {
    $return = $this->createReceivedReturn(['prefix' => 'RTN']);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('customers.returns.refresh', $return))
        ->assertForbidden()
        ->assertJson(['message' => 'Only super admins can refresh received returns']);
});
