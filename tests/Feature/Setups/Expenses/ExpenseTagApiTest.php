<?php

use App\Models\Setups\Expenses\ExpenseCategory;
use App\Models\Setups\Expenses\ExpenseTag;
use App\Models\User;

uses()->group('api', 'setup', 'setup.expenses', 'expense-tags');

it('lists tags and backfills the constant set on index', function () {
    $this->actingAs(User::factory()->create(), 'sanctum'); // super_admin default

    $this->getJson(route('setups.expenses.tags.index'))
        ->assertOk()
        ->assertJsonStructure(['message', 'data' => ['*' => ['id', 'name', 'code', 'is_system']], 'pagination']);

    foreach (ExpenseTag::SYSTEM_TAGS as $code => $name) {
        expect(ExpenseTag::where('code', $code)->exists())->toBeTrue();
    }
});

it('lets a developer create a tag', function () {
    $this->actingAs(User::factory()->developer()->create(), 'sanctum');

    $this->postJson(route('setups.expenses.tags.store'), ['name' => 'Fuel'])
        ->assertCreated()
        ->assertJsonPath('data.code', 'fuel')
        ->assertJsonPath('data.is_system', false);
});

it('forbids a non-developer from creating a tag', function () {
    $this->actingAs(User::factory()->admin()->create(), 'sanctum');

    $this->postJson(route('setups.expenses.tags.store'), ['name' => 'Fuel'])
        ->assertForbidden();
});

it('blocks deleting a system tag', function () {
    $this->actingAs(User::factory()->developer()->create(), 'sanctum');
    ExpenseTag::ensureSystemTags();
    $rent = ExpenseTag::where('code', 'rent')->first();

    $this->deleteJson(route('setups.expenses.tags.destroy', $rent))
        ->assertStatus(422);

    expect(ExpenseTag::find($rent->id))->not->toBeNull();
});

it('rejects renaming a system tag', function () {
    $this->actingAs(User::factory()->developer()->create(), 'sanctum');
    ExpenseTag::ensureSystemTags();
    $rent = ExpenseTag::where('code', 'rent')->first();

    $this->putJson(route('setups.expenses.tags.update', $rent), ['name' => 'Renamed'])
        ->assertStatus(422);

    expect($rent->fresh()->name)->toBe('Rent');
});

it('lets a super-admin (non-developer) sync categories to a tag', function () {
    $this->actingAs(User::factory()->create(), 'sanctum'); // super_admin
    $tag = ExpenseTag::factory()->create();
    $a = ExpenseCategory::factory()->create();
    $b = ExpenseCategory::factory()->create();

    $this->putJson(route('setups.expenses.tags.categories', $tag), ['category_ids' => [$a->id, $b->id]])
        ->assertOk();

    expect($tag->fresh()->categoryIds())->toEqualCanonicalizing([$a->id, $b->id]);
});
