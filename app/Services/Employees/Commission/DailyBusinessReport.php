<?php

namespace App\Services\Employees\Commission;

use App\Models\Customers\CustomerPayment;
use App\Models\Customers\CustomerReturn;
use App\Models\Customers\Sale;
use App\Models\Setups\TaxCode;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Day-by-day business grid for an employee's OWN transactions in a month, used by the
 * ?detailed=true view. Day cells hold ORIGINAL (gross, tax-inclusive) amounts per prefix;
 * the totals footer then shows the gross totals, the same figures with VAT removed, the
 * net month totals, and the balance (payments − sales − returns).
 *
 * Returns: ['days' => [...per day...], 'totals' => [...footer...]].
 */
class DailyBusinessReport
{
    private const SALE_PREFIXES    = [Sale::TAXPREFIX, Sale::TAXFREEPREFIX];                     // INV, INX
    private const PAYMENT_PREFIXES = [CustomerPayment::TAXPREFIX, CustomerPayment::TAXFREEPREFIX]; // RCT, RCX
    private const RETURN_PREFIXES  = [CustomerReturn::TAXPREFIX, CustomerReturn::TAXFREEPREFIX];   // RTN, RTX

    /** @return array{days: array<int, array<string, mixed>>, totals: array<string, mixed>} */
    public function forEmployee(int $employeeId, int $month, int $year): array
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $sales = $this->grossByDayAndPrefix(
            Sale::query()->approved()->where('salesperson_id', $employeeId)->whereBetween('date', [$start, $end]),
            'total_usd'
        );
        $payments = $this->grossByDayAndPrefix(
            CustomerPayment::query()->approved()->where('salesperson_id', $employeeId)->whereBetween('date', [$start, $end]),
            'amount_usd'
        );
        $returns = $this->grossByDayAndPrefix(
            CustomerReturn::query()->approved()->received()->where('salesperson_id', $employeeId)->whereBetween('date', [$start, $end]),
            'total_usd'
        );

        return [
            'days'   => $this->buildDays($year, $month, $sales, $payments, $returns),
            'totals' => $this->buildTotals($sales, $payments, $returns),
        ];
    }

    /**
     * @return array<string, array<string, array{count: int, total: float}>> [date][prefix] => cell
     */
    private function grossByDayAndPrefix(Builder $query, string $amountColumn): array
    {
        $rows = $query
            ->selectRaw("DATE(date) as transaction_date, prefix, COUNT(*) as count, SUM({$amountColumn}) as total")
            ->groupBy('transaction_date', 'prefix')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[$row->transaction_date][$row->prefix] = [
                'count' => (int) $row->count,
                'total' => round((float) $row->total, 2),
            ];
        }

        return $out;
    }

    private function buildDays(int $year, int $month, array $sales, array $payments, array $returns): array
    {
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;

        $days = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);

            $days[] = [
                'day'      => $day,
                'date'     => $date,
                'sales'    => $this->cells($sales[$date] ?? [], self::SALE_PREFIXES),
                'payments' => $this->cells($payments[$date] ?? [], self::PAYMENT_PREFIXES),
                'returns'  => $this->cells($returns[$date] ?? [], self::RETURN_PREFIXES),
            ];
        }

        return $days;
    }

    /** Zero-fill every prefix so the grid columns are always present. */
    private function cells(array $byPrefix, array $prefixes): array
    {
        $out = [];
        foreach ($prefixes as $prefix) {
            $out[$prefix] = $byPrefix[$prefix] ?? ['count' => 0, 'total' => 0];
        }

        return $out;
    }

    private function buildTotals(array $sales, array $payments, array $returns): array
    {
        $divisor = $this->vatDivisor();

        $salesGross    = $this->sumByPrefix($sales, self::SALE_PREFIXES);
        $paymentsGross = $this->sumByPrefix($payments, self::PAYMENT_PREFIXES);
        $returnsGross  = $this->sumByPrefix($returns, self::RETURN_PREFIXES);

        $salesNet    = $this->netByPrefix($salesGross, Sale::TAXPREFIX, $divisor);
        $paymentsNet = $this->netByPrefix($paymentsGross, CustomerPayment::TAXPREFIX, $divisor);
        $returnsNet  = $this->netByPrefix($returnsGross, CustomerReturn::TAXPREFIX, $divisor);

        $salesMonth    = round(array_sum(array_column($salesNet, 'total')), 2);
        $paymentsMonth = round(array_sum(array_column($paymentsNet, 'total')), 2);
        $returnsMonth  = round(array_sum(array_column($returnsNet, 'total')), 2);

        return [
            'vat_rate'      => $divisor,
            'gross'         => ['sales' => $salesGross, 'returns' => $returnsGross, 'payments' => $paymentsGross],
            'without_vat'   => ['sales' => $salesNet, 'returns' => $returnsNet, 'payments' => $paymentsNet],
            'month_total'   => ['sales' => $salesMonth, 'returns' => $returnsMonth, 'payments' => $paymentsMonth],
            'total_balance' => round($paymentsMonth - $salesMonth - $returnsMonth, 2),
        ];
    }

    /** Sum gross count + total across all days, per prefix. */
    private function sumByPrefix(array $byDay, array $prefixes): array
    {
        $out = [];
        foreach ($prefixes as $prefix) {
            $out[$prefix] = ['count' => 0, 'total' => 0.0];
        }

        foreach ($byDay as $prefixRows) {
            foreach ($prefixRows as $prefix => $cell) {
                if (! isset($out[$prefix])) {
                    continue;
                }
                $out[$prefix]['count'] += $cell['count'];
                $out[$prefix]['total'] += $cell['total'];
            }
        }

        foreach ($out as $prefix => $cell) {
            $out[$prefix]['total'] = round($cell['total'], 2);
        }

        return $out;
    }

    /** Same figures with VAT removed: the tax prefix is divided by the divisor, tax-free stays. */
    private function netByPrefix(array $grossByPrefix, string $taxPrefix, float $divisor): array
    {
        $out = [];
        foreach ($grossByPrefix as $prefix => $cell) {
            $total = $prefix === $taxPrefix ? $cell['total'] / $divisor : $cell['total'];
            $out[$prefix] = ['total' => round($total, 2)];
        }

        return $out;
    }

    /** 1 + the default tax code's rate (e.g. 11% -> 1.11); 1 when no default is configured. */
    private function vatDivisor(): float
    {
        $default = TaxCode::getDefault();

        return 1 + ($default ? (float) $default->tax_percent / 100 : 0.0);
    }
}
