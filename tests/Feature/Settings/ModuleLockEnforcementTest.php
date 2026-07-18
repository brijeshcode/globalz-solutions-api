<?php

use App\Models\Setting;
use App\Models\User;
use Tests\Feature\Customers\Sales\Concerns\HasSaleSetup;

uses(HasSaleSetup::class);

beforeEach(function () {
    $this->setUpSales();
    Setting::set('module_locks', 'sale', 7, Setting::TYPE_NUMBER);
    Setting::set('module_locks', 'sale_order', 7, Setting::TYPE_NUMBER);
});

it('blocks updating a locked sale', function () {
    $sale = $this->createApprovedSale(['date' => now()->subDays(30)]);
    $sale->updateQuietly(['status' => 'Delivered']); // Sale::creating forces status to Waiting

    $this->putJson(route('customers.sales.update', $sale), [])
        ->assertForbidden();
});

it('blocks deleting a locked sale', function () {
    $sale = $this->createApprovedSale(['date' => now()->subDays(30)]);
    $sale->updateQuietly(['status' => 'Delivered']); // Sale::creating forces status to Waiting

    $this->deleteJson(route('customers.sales.destroy', $sale))
        ->assertForbidden();
});

it('blocks changing status of a locked sale', function () {
    $sale = $this->createApprovedSale(['date' => now()->subDays(30)]);
    $sale->updateQuietly(['status' => 'Delivered']); // Sale::creating forces status to Waiting

    $this->patchJson(route('customers.sales.changeStatus', $sale), ['status' => 'Waiting'])
        ->assertForbidden();
});

it('does not block a recent delivered sale', function () {
    $sale = $this->createApprovedSale(['date' => now()]);
    $sale->updateQuietly(['status' => 'Delivered']); // Sale::creating forces status to Waiting

    expect($this->deleteJson(route('customers.sales.destroy', $sale))->status())
        ->not->toBe(403);
});

it('does not block an old sale that is not yet delivered', function () {
    $sale = $this->createApprovedSale(['date' => now()->subDays(30), 'status' => 'Waiting']);

    expect($this->deleteJson(route('customers.sales.destroy', $sale))->status())
        ->not->toBe(403);
});

it('does not block when the module lock is disabled', function () {
    Setting::set('module_locks', 'sale', 0, Setting::TYPE_NUMBER);
    $sale = $this->createApprovedSale(['date' => now()->subDays(30)]);
    $sale->updateQuietly(['status' => 'Delivered']); // Sale::creating forces status to Waiting

    expect($this->deleteJson(route('customers.sales.destroy', $sale))->status())
        ->not->toBe(403);
});

it('lets a super admin modify a locked sale', function () {
    $sale = $this->createApprovedSale(['date' => now()->subDays(30)]);
    $sale->updateQuietly(['status' => 'Delivered']); // Sale::creating forces status to Waiting

    $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $this->actingAs($superAdmin, 'sanctum');

    expect($this->deleteJson(route('customers.sales.destroy', $sale))->status())
        ->not->toBe(403);
});

it('blocks updating a locked pending sale order via its own key', function () {
    $order = \App\Models\Customers\Sale::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'currency_id'  => $this->currency->id,
        'approved_by'  => null,
        'approved_at'  => null,
        'date'         => now()->subDays(30),
    ]);

    $this->putJson(route('customers.sale-orders.update', $order), [])
        ->assertForbidden();
});

it('does not lock sale orders when only the sale key is enabled', function () {
    Setting::set('module_locks', 'sale_order', 0, Setting::TYPE_NUMBER);

    $order = \App\Models\Customers\Sale::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'currency_id'  => $this->currency->id,
        'approved_by'  => null,
        'approved_at'  => null,
        'date'         => now()->subDays(30),
    ]);

    expect($this->putJson(route('customers.sale-orders.update', $order), [])->status())
        ->not->toBe(403);
});
