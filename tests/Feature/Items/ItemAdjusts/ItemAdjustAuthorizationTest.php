<?php

use App\Models\Items\ItemAdjust;
use App\Models\User;
use Tests\Feature\Items\ItemAdjusts\Concerns\HasItemAdjustSetup;

uses(HasItemAdjustSetup::class);

beforeEach(function () {
    $this->setUpItemAdjusts();
    $this->setupInventory($this->item1->id, 50);
});

it('denies salesman to list item adjusts', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_SALESMAN]), 'sanctum');
    $this->getJson(route('items.adjusts.index'))->assertForbidden();
});

it('denies warehouse manager to list item adjusts', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_WAREHOUSE_MANAGER]), 'sanctum');
    $this->getJson(route('items.adjusts.index'))->assertForbidden();
});

it('denies salesman to create item adjust', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_SALESMAN]), 'sanctum');
    $this->postJson(route('items.adjusts.store'), $this->adjustPayload())->assertForbidden();
});

it('denies warehouse manager to create item adjust', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_WAREHOUSE_MANAGER]), 'sanctum');
    $this->postJson(route('items.adjusts.store'), $this->adjustPayload())->assertForbidden();
});

it('allows admin to create item adjust', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]), 'sanctum');
    $this->postJson(route('items.adjusts.store'), $this->adjustPayload())->assertCreated();
});

it('allows super admin to create item adjust', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]), 'sanctum');
    $this->postJson(route('items.adjusts.store'), $this->adjustPayload())->assertCreated();
});

it('allows developer to create item adjust', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_DEVELOPER]), 'sanctum');
    $this->postJson(route('items.adjusts.store'), $this->adjustPayload())->assertCreated();
});

it('allows admin to update item adjust', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]), 'sanctum');
    $adjust = ItemAdjust::factory()->create(['warehouse_id' => $this->warehouse->id]);

    $this->putJson(route('items.adjusts.update', $adjust), ['items' => [['item_id' => $this->item1->id, 'quantity' => 5]]])
        ->assertOk();
});

it('denies salesman to update item adjust', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_SALESMAN]), 'sanctum');
    $adjust = ItemAdjust::factory()->create(['warehouse_id' => $this->warehouse->id]);

    $this->putJson(route('items.adjusts.update', $adjust), ['items' => [['item_id' => $this->item1->id, 'quantity' => 5]]])
        ->assertForbidden();
});

it('allows admin to delete item adjust', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]), 'sanctum');
    $adjust = ItemAdjust::factory()->create(['warehouse_id' => $this->warehouse->id]);

    $this->deleteJson(route('items.adjusts.destroy', $adjust))->assertStatus(204);
});

it('denies salesman to delete item adjust', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_SALESMAN]), 'sanctum');
    $adjust = ItemAdjust::factory()->create(['warehouse_id' => $this->warehouse->id]);

    $this->deleteJson(route('items.adjusts.destroy', $adjust))->assertForbidden();
});

it('denies warehouse manager to delete item adjust', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_WAREHOUSE_MANAGER]), 'sanctum');
    $adjust = ItemAdjust::factory()->create(['warehouse_id' => $this->warehouse->id]);

    $this->deleteJson(route('items.adjusts.destroy', $adjust))->assertForbidden();
});
