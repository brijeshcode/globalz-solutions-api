<?php

use App\Models\Expenses\ExpenseTransaction;
use App\Models\Setups\Expenses\ExpenseCategory;
use App\Models\Setups\Expenses\ExpenseTag;
use App\Models\User;

uses()->group('api', 'expenses', 'expense-tags');

it('filters the transactions list by tag_id', function () {
    $this->actingAs(User::factory()->create(), 'sanctum');

    $rentCat = ExpenseCategory::factory()->create();
    $otherCat = ExpenseCategory::factory()->create();
    $tag = ExpenseTag::factory()->create();
    $tag->categories()->sync([$rentCat->id]);

    $mine = ExpenseTransaction::factory()->create(['expense_category_id' => $rentCat->id]);
    ExpenseTransaction::factory()->create(['expense_category_id' => $otherCat->id]);

    $response = $this->getJson(route('expense-transactions.index', ['tag_id' => $tag->id]))->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toEqual([$mine->id]);
});

it('returns no rows for a tag with no mapped categories', function () {
    $this->actingAs(User::factory()->create(), 'sanctum');

    $cat = ExpenseCategory::factory()->create();
    ExpenseTransaction::factory()->create(['expense_category_id' => $cat->id]);
    $emptyTag = ExpenseTag::factory()->create();

    $response = $this->getJson(route('expense-transactions.index', ['tag_id' => $emptyTag->id]))->assertOk();

    expect($response->json('data'))->toBe([]);
});
