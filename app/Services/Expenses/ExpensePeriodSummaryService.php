<?php

namespace App\Services\Expenses;

use App\Models\Setups\Expenses\ExpenseCategory;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds a weekly / monthly / yearly expense trend from a (possibly filtered)
 * ExpenseTransaction query. Aggregates live from source — no stored summary table —
 * so totals can never drift from the underlying transactions.
 */
class ExpensePeriodSummaryService
{
    public const PERIOD_WEEKLY  = 'weekly';
    public const PERIOD_MONTHLY = 'monthly';
    public const PERIOD_YEARLY  = 'yearly';

    public const MAX_COUNT = 60;

    /**
     * @param  Builder<\App\Models\Expenses\ExpenseTransaction>  $baseQuery
     * @return array{period_type: string, count: int, periods: array<int, array<string, mixed>>}
     */
    public function summarize(Builder $baseQuery, string $periodType, int $count): array
    {
        $count = max(1, min($count, self::MAX_COUNT));

        // Pre-build the buckets (oldest -> newest, zero-filled) so the caller always
        // gets exactly $count periods regardless of whether data exists in each one.
        $buckets = $this->buildBuckets($periodType, $count);

        $spanStart = $buckets[0]['start'];
        $spanEnd   = $buckets[count($buckets) - 1]['end'];

        $periodExpr = $this->periodKeyExpression($periodType);

        $rows = (clone $baseQuery)
            ->whereBetween('expense_transactions.date', [$spanStart, $spanEnd])
            ->selectRaw("
                {$periodExpr} as period_key,
                expense_transactions.expense_category_id as expense_category_id,
                SUM(expense_transactions.amount + COALESCE(expense_transactions.vat_amount, 0)) as total,
                SUM(expense_transactions.amount_usd + COALESCE(expense_transactions.vat_amount_usd, 0)) as total_usd
            ")
            ->groupByRaw("{$periodExpr}, expense_transactions.expense_category_id")
            ->get();

        $categoryLookup = $this->categoryLookup($rows->pluck('expense_category_id'));

        // Index the aggregated rows by their period key for O(1) bucket assignment.
        $rowsByKey = $rows->groupBy(fn ($row) => (string) $row->period_key);

        $periods = [];
        foreach ($buckets as $bucket) {
            $periods[] = $this->buildPeriod($bucket, $rowsByKey->get($bucket['key'], collect()), $categoryLookup);
        }

        return [
            'period_type' => $periodType,
            'count'       => $count,
            'periods'     => $periods,
        ];
    }

    /**
     * @return array<int, array{key: string, start: Carbon, end: Carbon}>
     */
    private function buildBuckets(string $periodType, int $count): array
    {
        $now = Carbon::now();
        $buckets = [];

        // Walk backwards from the current period, then reverse to oldest -> newest.
        for ($i = 0; $i < $count; $i++) {
            $buckets[] = $this->bucketAt($periodType, $now, $i);
        }

        return array_reverse($buckets);
    }

    /**
     * @return array{key: string, start: Carbon, end: Carbon}
     */
    private function bucketAt(string $periodType, CarbonInterface $now, int $periodsAgo): array
    {
        switch ($periodType) {
            case self::PERIOD_WEEKLY:
                $start = $now->copy()->startOfWeek(Carbon::MONDAY)->subWeeks($periodsAgo);
                $end   = $start->copy()->endOfWeek(Carbon::SUNDAY);
                $key   = $start->format('oW'); // ISO year + ISO week, matches YEARWEEK(date, 3)
                break;

            case self::PERIOD_YEARLY:
                $start = $now->copy()->startOfYear()->subYears($periodsAgo);
                $end   = $start->copy()->endOfYear();
                $key   = $start->format('Y'); // matches YEAR(date)
                break;

            case self::PERIOD_MONTHLY:
            default:
                $start = $now->copy()->startOfMonth()->subMonthsNoOverflow($periodsAgo);
                $end   = $start->copy()->endOfMonth();
                $key   = $start->format('Y-m'); // matches DATE_FORMAT(date, '%Y-%m')
                break;
        }

        return ['key' => $key, 'start' => $start, 'end' => $end];
    }

    private function periodKeyExpression(string $periodType): string
    {
        return match ($periodType) {
            self::PERIOD_WEEKLY => "YEARWEEK(expense_transactions.date, 3)",
            self::PERIOD_YEARLY => "YEAR(expense_transactions.date)",
            default             => "DATE_FORMAT(expense_transactions.date, '%Y-%m')",
        };
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $categoryIds
     * @return \Illuminate\Support\Collection<int, ExpenseCategory>
     */
    private function categoryLookup($categoryIds)
    {
        $ids = $categoryIds->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return ExpenseCategory::whereIn('id', $ids)
            ->get(['id', 'name', 'parent_id'])
            ->keyBy('id');
    }

    /**
     * @param  array{key: string, start: Carbon, end: Carbon}  $bucket
     * @param  \Illuminate\Support\Collection<int, mixed>  $rows
     * @param  \Illuminate\Support\Collection<int, ExpenseCategory>  $categoryLookup
     * @return array<string, mixed>
     */
    private function buildPeriod(array $bucket, $rows, $categoryLookup): array
    {
        $total     = 0.0;
        $totalUsd  = 0.0;
        $categories = [];

        foreach ($rows as $row) {
            $rowTotal    = (float) $row->total;
            $rowTotalUsd = (float) $row->total_usd;

            $total    += $rowTotal;
            $totalUsd += $rowTotalUsd;

            $category = $categoryLookup->get($row->expense_category_id);

            $categories[] = [
                'category_id' => (int) $row->expense_category_id,
                'name'        => $category->name ?? null,
                'parent_id'   => $category->parent_id ?? null,
                'total'       => round($rowTotal, 2),
                'total_usd'   => round($rowTotalUsd, 8),
            ];
        }

        return [
            'key'        => $bucket['key'],
            'start_date' => $bucket['start']->toDateString(),
            'end_date'   => $bucket['end']->toDateString(),
            'total'      => round($total, 2),
            'total_usd'  => round($totalUsd, 8),
            'categories' => $categories,
        ];
    }
}
