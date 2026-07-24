<?php

namespace App\Services\Employees\Commission;

use App\Models\Customers\CustomerPayment;
use App\Models\Customers\CustomerReturn;
use App\Models\Customers\Sale;
use App\Models\Employees\CommissionTargetRule;
use App\Models\Setups\TaxCode;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * The only class that touches the database. Returns net-of-VAT totals per dataset
 * (sales / payments / returns) filtered by include type and date range, with a
 * per-day/per-prefix breakdown. Results are memoized so multiple rules over the
 * same dataset don't re-query.
 *
 * Each method returns: ['amount' => float, 'daily' => array<int, array{date,prefix,count,total}>]
 */
class TransactionAggregator
{
    /** @var array<string, array{amount: float, daily: array}> */
    private array $cache = [];

    /** Divisor used to strip VAT from tax-inclusive amounts: 1 + (default tax rate). */
    private ?float $vatDivisor = null;

    public function sales(int $employeeId, string $includeType, Carbon $start, Carbon $end): array
    {
        return $this->remember('sales', $employeeId, $includeType, $start, $end, function () use ($employeeId, $includeType, $start, $end) {
            $query = Sale::query()->approved()->byDateRange($start, $end);
            $this->filterBySalesperson($query, $employeeId, $includeType);

            return $this->netByPrefix($query, 'total_usd', Sale::TAXPREFIX);
        });
    }

    public function returns(int $employeeId, string $includeType, Carbon $start, Carbon $end): array
    {
        return $this->remember('returns', $employeeId, $includeType, $start, $end, function () use ($employeeId, $includeType, $start, $end) {
            $query = CustomerReturn::query()->approved()->received()->byDateRange($start, $end);
            $this->filterBySalesperson($query, $employeeId, $includeType);

            return $this->netByPrefix($query, 'total_usd', CustomerReturn::TAXPREFIX);
        });
    }

    public function payments(int $employeeId, string $includeType, Carbon $start, Carbon $end): array
    {
        return $this->remember('payments', $employeeId, $includeType, $start, $end, function () use ($employeeId, $includeType, $start, $end) {
            $query = CustomerPayment::query()->approved()->byDateRange($start, $end);
            $this->filterBySalesperson($query, $employeeId, $includeType);

            return $this->netByPrefix($query, 'amount_usd', CustomerPayment::TAXPREFIX);
        });
    }

    /**
     * 1 + the default tax code's rate (e.g. an 11% default -> 1.11). Resolved once per instance.
     * When no default tax code is configured, there is no VAT to strip, so the divisor is 1.
     */
    private function vatDivisor(): float
    {
        if ($this->vatDivisor === null) {
            $default = TaxCode::getDefault();
            $this->vatDivisor = 1 + ($default ? (float) $default->tax_percent / 100 : 0.0);
        }

        return $this->vatDivisor;
    }

    private function remember(string $dataset, int $employeeId, string $includeType, Carbon $start, Carbon $end, callable $resolver): array
    {
        $key = "{$dataset}:{$employeeId}:{$includeType}:{$start->toDateString()}:{$end->toDateString()}";

        return $this->cache[$key] ??= $resolver();
    }

    /**
     * All three datasets now filter directly on the record's own salesperson_id column
     * (payments snapshot it from the customer at creation time).
     */
    private function filterBySalesperson(Builder $query, int $employeeId, string $includeType): void
    {
        if ($includeType === CommissionTargetRule::INCLUDE_TYPE_OWN) {
            $query->where('salesperson_id', $employeeId);
        } elseif ($includeType === CommissionTargetRule::INCLUDE_TYPE_ALL_EXCEPT_OWN) {
            $query->where('salesperson_id', '!=', $employeeId);
        }
        // INCLUDE_TYPE_ALL: no filter
    }

    /**
     * Group by day + prefix, then exclude VAT: tax-prefixed (tax-inclusive) amounts are divided
     * by (1 + default tax rate), everything else (tax-free) is taken as-is.
     *
     * @return array{amount: float, daily: array<int, array{date: string, prefix: string, count: int, total: float}>}
     */
    private function netByPrefix(Builder $query, string $amountColumn, string $taxPrefix): array
    {
        $rows = $query
            ->selectRaw("DATE(date) as transaction_date, prefix, COUNT(*) as count, SUM({$amountColumn}) as total")
            ->groupBy('transaction_date', 'prefix')
            ->get();

        $divisor = $this->vatDivisor();

        $amount = 0.0;
        $daily = [];

        foreach ($rows as $row) {
            $net = $row->prefix === $taxPrefix ? (float) $row->total / $divisor : (float) $row->total;
            $amount += $net;

            $daily[] = [
                'date'   => $row->transaction_date,
                'prefix' => $row->prefix,
                'count'  => (int) $row->count,
                'total'  => $net,
            ];
        }

        return ['amount' => $amount, 'daily' => $daily];
    }
}
