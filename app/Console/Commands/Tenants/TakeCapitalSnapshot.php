<?php

namespace App\Console\Commands\Tenants;

use App\Http\Controllers\Api\Reports\Finance\CapitalReportController;
use App\Models\Reports\CapitalSnapshot;
use App\Models\Tenant;
use Illuminate\Console\Command;

class TakeCapitalSnapshot extends Command
{
    protected $signature = 'capital:snapshot {--month= : Month (1-12), defaults to previous month} {--year= : Year, defaults to previous month\'s year}';

    protected $description = 'Take a snapshot of the capital report for end-of-month tracking (runs per tenant)';

    public function handle(): int
    {
        $previousMonth = now()->subMonthNoOverflow();
        $month = (int) ($this->option('month') ?? $previousMonth->month);
        $year  = (int) ($this->option('year') ?? $previousMonth->year);

        $this->info("Taking capital snapshot for {$year}-{$month}...");

        Tenant::runForEachActive('Capital snapshot', function (Tenant $tenant) use ($month, $year) {
            $report = app(CapitalReportController::class)->calculateCapitalReport();

            CapitalSnapshot::updateOrCreate(
                ['year' => $year, 'month' => $month],
                [
                    'available_stock_value'    => $report['available_stock_value'],
                    'tax_free_purchases_total' => $report['tax_free_purchases_total'],
                    'total_vat_on_stock'        => $report['total_vat_on_stock'],
                    'vat_paid_on_purchase'      => $report['vat_paid_on_purchase'],
                    'default_tax_percent'       => $report['default_tax_percent'],
                    'vat_on_current_stock'      => $report['vat_on_current_stock'],
                    'pending_purchases_value'   => $report['pending_purchases_value'],
                    'net_stock_value'            => $report['net_stock_value'],
                    'unpaid_customer_balance'   => $report['unpaid_customer_balance'],
                    'unapproved_payments'        => $report['unapproved_payment_orders'],
                    'accounts_balance'           => $report['money_in_all_accounts'],
                    'net_capital'                => $report['net_capital'],
                    'debt_account'               => $report['debt_account'],
                    'final_result'               => $report['final_result'],
                ]
            );

            $this->info("  ✓ {$tenant->tenant_key} — Net Capital: {$report['net_capital']}, Final Result: {$report['final_result']}");

            return ['year' => $year, 'month' => $month, 'net_capital' => $report['net_capital']];
        });

        return self::SUCCESS;
    }
}
