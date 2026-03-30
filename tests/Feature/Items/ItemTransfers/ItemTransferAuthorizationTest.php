<?php

use App\Models\Items\ItemTransfer;
use App\Models\User;
use Tests\Feature\Items\ItemTransfers\Concerns\HasItemTransferSetup;

uses(HasItemTransferSetup::class);

beforeEach(function () {
    $this->setUpItemTransfers();
    $this->setupInventory($this->item1->id, $this->fromWarehouse->id, 50);
});

it('denies salesman to create item transfer', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_SALESMAN]), 'sanctum');
    $this->postJson(route('items.transfers.store'), $this->transferPayload())->assertForbidden();
});

it('denies warehouse manager to create item transfer', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_WAREHOUSE_MANAGER]), 'sanctum');
    $this->postJson(route('items.transfers.store'), $this->transferPayload())->assertForbidden();
});

it('allows admin to create item transfer', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]), 'sanctum');
    $this->postJson(route('items.transfers.store'), $this->transferPayload())->assertCreated();
});

it('allows super admin to create item transfer', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]), 'sanctum');
    $this->postJson(route('items.transfers.store'), $this->transferPayload())->assertCreated();
});

it('allows developer to create item transfer', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_DEVELOPER]), 'sanctum');
    $this->postJson(route('items.transfers.store'), $this->transferPayload())->assertCreated();
});

it('allows admin to update item transfer', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]), 'sanctum');
    $transfer = ItemTransfer::factory()->create(['from_warehouse_id' => $this->fromWarehouse->id, 'to_warehouse_id' => $this->toWarehouse->id]);

    $this->putJson(route('items.transfers.update', $transfer), ['items' => [['item_id' => $this->item1->id, 'quantity' => 5]]])
        ->assertOk();
});

it('denies salesman to update item transfer', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_SALESMAN]), 'sanctum');
    $transfer = ItemTransfer::factory()->create(['from_warehouse_id' => $this->fromWarehouse->id, 'to_warehouse_id' => $this->toWarehouse->id]);

    $this->putJson(route('items.transfers.update', $transfer), ['items' => [['item_id' => $this->item1->id, 'quantity' => 5]]])
        ->assertForbidden();
});

it('allows admin to delete item transfer', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]), 'sanctum');
    $transfer = ItemTransfer::factory()->create(['from_warehouse_id' => $this->fromWarehouse->id, 'to_warehouse_id' => $this->toWarehouse->id]);

    $this->deleteJson(route('items.transfers.destroy', $transfer))->assertStatus(204);
});

it('denies salesman to delete item transfer', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_SALESMAN]), 'sanctum');
    $transfer = ItemTransfer::factory()->create(['from_warehouse_id' => $this->fromWarehouse->id, 'to_warehouse_id' => $this->toWarehouse->id]);

    $this->deleteJson(route('items.transfers.destroy', $transfer))->assertForbidden();
});

it('denies warehouse manager to delete item transfer', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_WAREHOUSE_MANAGER]), 'sanctum');
    $transfer = ItemTransfer::factory()->create(['from_warehouse_id' => $this->fromWarehouse->id, 'to_warehouse_id' => $this->toWarehouse->id]);

    $this->deleteJson(route('items.transfers.destroy', $transfer))->assertForbidden();
});
