<?php

use App\Models\Setups\Expenses\ExpenseCategory;
use Carbon\Carbon;
use Tests\Feature\Accounts\ExpenseTransactions\Concerns\HasExpenseTransactionSetup;

uses(HasExpenseTransactionSetup::class);

beforeEach(function () {
    $this->setUpExpenseTransactions();
});

it('returns a monthly trend with one bucket per month when the flag is on', function () {
    // Two transactions this month, one last month.
    $thisMonth = Carbon::now()->startOfMonth()->addDays(2);
    $lastMonth = Carbon::now()->subMonthNoOverflow()->startOfMonth()->addDays(2);

    $this->createTransaction(['date' => $thisMonth->toDateString(), 'amount' => 100, 'amount_usd' => 100, 'vat_amount' => 10, 'vat_amount_usd' => 10]);
    $this->createTransaction(['date' => $thisMonth->toDateString(), 'amount' => 200, 'amount_usd' => 200, 'vat_amount' => 0, 'vat_amount_usd' => 0]);
    $this->createTransaction(['date' => $lastMonth->toDateString(), 'amount' => 50, 'amount_usd' => 50, 'vat_amount' => 5, 'vat_amount_usd' => 5]);

    $meta = $this->getJson(route('expense-transactions.index', ['trend' => 1, 'trend_period' => 'monthly', 'trend_count' => 12]))
        ->assertOk()
        ->assertJsonStructure([
            'meta' => ['trend' => ['period_type', 'count', 'periods' => ['*' => ['key', 'start_date', 'end_date', 'total', 'total_usd', 'categories']]]],
        ])
        ->json('meta.trend');

    expect($meta['period_type'])->toBe('monthly')
        ->and($meta['count'])->toBe(12)
        ->and($meta['periods'])->toHaveCount(12);

    // Newest bucket is last (oldest -> newest order): current month total = (100+10) + 200 = 310.
    $current = $meta['periods'][11];
    expect($current['key'])->toBe(Carbon::now()->format('Y-m'))
        ->and((float) $current['total'])->toBe(310.0)
        ->and((float) $current['total_usd'])->toBe(310.0);

    // Previous bucket total = 50 + 5 = 55.
    $previous = $meta['periods'][10];
    expect($previous['key'])->toBe(Carbon::now()->subMonthNoOverflow()->format('Y-m'))
        ->and((float) $previous['total'])->toBe(55.0);
});

it('omits the trend when the flag is absent', function () {
    $this->createTransaction(['amount' => 100, 'amount_usd' => 100]);

    $this->getJson(route('expense-transactions.index'))
        ->assertOk()
        ->assertJsonMissingPath('meta.trend');
});

it('zero-fills months that have no transactions', function () {
    // Only the current month has data; every other bucket must still appear with total 0.
    $this->createTransaction(['date' => Carbon::now()->startOfMonth()->addDay()->toDateString(), 'amount' => 100, 'amount_usd' => 100]);

    $periods = $this->getJson(route('expense-transactions.index', ['trend' => 1, 'trend_count' => 6]))
        ->assertOk()
        ->json('meta.trend.periods');

    expect($periods)->toHaveCount(6)
        ->and((float) $periods[0]['total'])->toBe(0.0)
        ->and($periods[0]['categories'])->toBe([])
        ->and((float) $periods[5]['total'])->toBe(100.0);
});

it('breaks each period total down into a flat list of categories', function () {
    $rent    = ExpenseCategory::factory()->create(['name' => 'Rent']);
    $fuel    = ExpenseCategory::factory()->create(['name' => 'Fuel']);
    $thisMonth = Carbon::now()->startOfMonth()->addDay()->toDateString();

    $this->createTransaction(['date' => $thisMonth, 'expense_category_id' => $rent->id, 'amount' => 300, 'amount_usd' => 300]);
    $this->createTransaction(['date' => $thisMonth, 'expense_category_id' => $fuel->id, 'amount' => 120, 'amount_usd' => 120]);

    $current = collect(
        $this->getJson(route('expense-transactions.index', ['trend' => 1, 'trend_count' => 3]))
            ->assertOk()
            ->json('meta.trend.periods')
    )->last();

    $byId = collect($current['categories'])->keyBy('category_id');

    expect($current['categories'])->toHaveCount(2)
        ->and((float) $byId[$rent->id]['total'])->toBe(300.0)
        ->and($byId[$rent->id]['name'])->toBe('Rent')
        ->and((float) $byId[$fuel->id]['total'])->toBe(120.0);
});

it('scopes the trend to a single category when expense_category_id is filtered', function () {
    $rent = ExpenseCategory::factory()->create(['name' => 'Rent']);
    $fuel = ExpenseCategory::factory()->create(['name' => 'Fuel']);
    $thisMonth = Carbon::now()->startOfMonth()->addDay()->toDateString();

    $this->createTransaction(['date' => $thisMonth, 'expense_category_id' => $rent->id, 'amount' => 300, 'amount_usd' => 300]);
    $this->createTransaction(['date' => $thisMonth, 'expense_category_id' => $fuel->id, 'amount' => 120, 'amount_usd' => 120]);

    $current = collect(
        $this->getJson(route('expense-transactions.index', ['trend' => 1, 'trend_count' => 3, 'expense_category_id' => $rent->id]))
            ->assertOk()
            ->json('meta.trend.periods')
    )->last();

    expect((float) $current['total'])->toBe(300.0)
        ->and($current['categories'])->toHaveCount(1)
        ->and($current['categories'][0]['category_id'])->toBe($rent->id);
});

it('supports a weekly trend with iso week buckets', function () {
    $this->createTransaction(['date' => Carbon::now()->startOfWeek(Carbon::MONDAY)->addDay()->toDateString(), 'amount' => 80, 'amount_usd' => 80]);

    $trend = $this->getJson(route('expense-transactions.index', ['trend' => 1, 'trend_period' => 'weekly', 'trend_count' => 8]))
        ->assertOk()
        ->json('meta.trend');

    expect($trend['period_type'])->toBe('weekly')
        ->and($trend['periods'])->toHaveCount(8);

    $current = collect($trend['periods'])->last();
    expect($current['key'])->toBe(Carbon::now()->format('oW'))
        ->and((float) $current['total'])->toBe(80.0);
});

it('supports a yearly trend', function () {
    $this->createTransaction(['date' => Carbon::now()->startOfYear()->addMonth()->toDateString(), 'amount' => 500, 'amount_usd' => 500]);

    $trend = $this->getJson(route('expense-transactions.index', ['trend' => 1, 'trend_period' => 'yearly', 'trend_count' => 3]))
        ->assertOk()
        ->json('meta.trend');

    $current = collect($trend['periods'])->last();
    expect($trend['period_type'])->toBe('yearly')
        ->and($trend['periods'])->toHaveCount(3)
        ->and($current['key'])->toBe(Carbon::now()->format('Y'))
        ->and((float) $current['total'])->toBe(500.0);
});

it('rejects an invalid trend_period', function () {
    $this->getJson(route('expense-transactions.index', ['trend' => 1, 'trend_period' => 'daily']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('trend_period');
});

it('rejects a trend_count above the maximum', function () {
    $this->getJson(route('expense-transactions.index', ['trend' => 1, 'trend_count' => 999]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('trend_count');
});
