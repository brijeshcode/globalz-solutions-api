<?php

use App\Models\Setups\Expenses\ExpenseCategory;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $this->actingAs($this->user, 'sanctum');

    $this->parent = ExpenseCategory::firstOrCreate(
        ['name' => 'Purchase Expenses'],
        ['is_active' => true, 'is_system' => true]
    );
});

it('lists purchase expense subcategories', function () {
    ExpenseCategory::factory()->create(['parent_id' => $this->parent->id, 'name' => 'Freight']);

    $this->getJson(route('setups.expenses.purchase-expense-subcategories.index'))
        ->assertOk()
        ->assertJsonFragment(['name' => 'Freight']);
});

it('creates a purchase expense subcategory', function () {
    $this->postJson(route('setups.expenses.purchase-expense-subcategories.store'), [
        'name'        => 'Port Handling',
        'description' => 'Port handling fees',
        'is_active'   => true,
    ])
        ->assertCreated()
        ->assertJsonFragment(['name' => 'Port Handling']);

    expect(ExpenseCategory::where('name', 'Port Handling')->where('parent_id', $this->parent->id)->exists())->toBeTrue();
});

it('updates a purchase expense subcategory', function () {
    $cat = ExpenseCategory::factory()->create(['parent_id' => $this->parent->id, 'name' => 'Old Name']);

    $this->putJson(route('setups.expenses.purchase-expense-subcategories.update', $cat), ['name' => 'New Name'])
        ->assertOk()
        ->assertJsonFragment(['name' => 'New Name']);
});

it('deletes a purchase expense subcategory', function () {
    $cat = ExpenseCategory::factory()->create(['parent_id' => $this->parent->id, 'name' => 'Temp']);

    $this->deleteJson(route('setups.expenses.purchase-expense-subcategories.destroy', $cat))
        ->assertOk();

    expect(ExpenseCategory::where('id', $cat->id)->whereNull('deleted_at')->exists())->toBeFalse();
});

it('blocks deletion when subcategory has linked expense transactions', function () {
    $cat = ExpenseCategory::factory()->create(['parent_id' => $this->parent->id, 'name' => 'Used']);

    \App\Models\Expenses\ExpenseTransaction::factory()->create(['expense_category_id' => $cat->id]);

    $this->deleteJson(route('setups.expenses.purchase-expense-subcategories.destroy', $cat))
        ->assertStatus(422)
        ->assertJsonStructure(['message']);
});

it('blocks deleting or editing the system parent category', function () {
    $this->deleteJson(route('setups.expenses.purchase-expense-subcategories.destroy', $this->parent))
        ->assertStatus(422);
});
