<?php

use App\Models\Expenses\ExpenseTransaction;
use App\Models\Setups\Expenses\ExpenseCategory;
use App\Models\Setups\Expenses\ExpenseTag;
use App\Models\User;
use Carbon\Carbon;

uses()->group('api', 'reports', 'expense-tags');

it('restricts the expense report to a tag\'s categories', function () {
    $this->actingAs(User::factory()->create(), 'sanctum');

    $date = Carbon::now()->startOfMonth()->addDay()->format('Y-m-d');
    $rentCat = ExpenseCategory::factory()->create(['exclude_from_profit' => false]);
    $otherCat = ExpenseCategory::factory()->create(['exclude_from_profit' => false]);

    ExpenseTransaction::factory()->create(['expense_category_id' => $rentCat->id, 'date' => $date, 'amount' => 100, 'vat_amount' => 0]);
    ExpenseTransaction::factory()->create(['expense_category_id' => $otherCat->id, 'date' => $date, 'amount' => 999, 'vat_amount' => 0]);

    $tag = ExpenseTag::factory()->create();
    $tag->categories()->sync([$rentCat->id]);

    $response = $this->getJson(route('reports.finance.expense', ['tag_id' => $tag->id]))->assertOk();

    // Only the rent category's 100 is counted; the 999 is excluded by the tag filter.
    expect((float) $response->json('data.expense_report.totals.total'))->toBe(100.0);
});
