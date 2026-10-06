<?php

use App\Models\Setups\Expenses\ExpenseCategory;
use App\Models\Setups\Expenses\ExpenseTag;

uses()->group('api', 'setup', 'setup.expenses', 'expense-tags');

it('backfills all constant system tags idempotently', function () {
    ExpenseTag::ensureSystemTags();
    ExpenseTag::ensureSystemTags(); // second call must not duplicate

    foreach (ExpenseTag::SYSTEM_TAGS as $code => $name) {
        expect(ExpenseTag::where('code', $code)->count())->toBe(1);
        expect(ExpenseTag::where('code', $code)->first())
            ->name->toBe($name)
            ->is_system->toBeTrue();
    }
});

it('resolves its mapped category ids', function () {
    $tag = ExpenseTag::factory()->create();
    $a = ExpenseCategory::factory()->create();
    $b = ExpenseCategory::factory()->create();
    $tag->categories()->sync([$a->id, $b->id]);

    expect($tag->categoryIds())->toEqualCanonicalizing([$a->id, $b->id]);
});

it('drops the pivot row but keeps the tag when a mapped category is deleted', function () {
    $tag = ExpenseTag::factory()->create();
    $cat = ExpenseCategory::factory()->create();
    $tag->categories()->sync([$cat->id]);

    $cat->forceDelete(); // hard delete triggers the FK cascade on the pivot

    expect(ExpenseTag::find($tag->id))->not->toBeNull();
    expect($tag->fresh()->categoryIds())->toBe([]);
});
