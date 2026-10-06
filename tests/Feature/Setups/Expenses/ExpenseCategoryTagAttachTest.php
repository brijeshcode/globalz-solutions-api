<?php

use App\Models\Setups\Expenses\ExpenseCategory;
use App\Models\Setups\Expenses\ExpenseTag;
use App\Models\User;

uses()->group('api', 'setup', 'setup.expenses', 'expense-tags');

beforeEach(function () {
    $this->actingAs(User::factory()->create(), 'sanctum'); // super_admin default
});

it('attaches tags when creating a category', function () {
    $a = ExpenseTag::factory()->create();
    $b = ExpenseTag::factory()->create();

    $response = $this->postJson(route('setups.expenses.categories.store'), [
        'name' => 'Office Rent',
        'expense_tag_ids' => [$a->id, $b->id],
    ])->assertCreated();

    $categoryId = $response->json('data.id');
    expect(ExpenseCategory::find($categoryId)->tags()->pluck('expense_tags.id')->all())
        ->toEqualCanonicalizing([$a->id, $b->id]);
    expect(collect($response->json('data.tags'))->pluck('id')->all())
        ->toEqualCanonicalizing([$a->id, $b->id]);
});

it('re-syncs tags when updating a category with expense_tag_ids', function () {
    $a = ExpenseTag::factory()->create();
    $b = ExpenseTag::factory()->create();
    $c = ExpenseTag::factory()->create();

    $category = ExpenseCategory::factory()->create();
    $category->tags()->sync([$a->id, $b->id]);

    $this->putJson(route('setups.expenses.categories.update', $category), [
        'name' => $category->name,
        'expense_tag_ids' => [$c->id], // replaces a,b with c
    ])->assertOk();

    expect($category->fresh()->tags()->pluck('expense_tags.id')->all())->toEqual([$c->id]);
});

it('leaves tags untouched when expense_tag_ids is omitted on update', function () {
    $a = ExpenseTag::factory()->create();
    $category = ExpenseCategory::factory()->create();
    $category->tags()->sync([$a->id]);

    $this->putJson(route('setups.expenses.categories.update', $category), [
        'name' => 'Renamed Category',
    ])->assertOk();

    expect($category->fresh()->tags()->pluck('expense_tags.id')->all())->toEqual([$a->id]);
});
