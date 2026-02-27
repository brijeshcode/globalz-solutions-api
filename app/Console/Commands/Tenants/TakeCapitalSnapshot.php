<?php

namespace App\Console\Commands\Tenants;

use App\Http\Controllers\Api\Reports\Finance\CapitalReportController;
use App\Models\Reports\CapitalSnapshot;
use Illuminate\Console\Command;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

/**
 * Multi-Tenant Scheduling Notes:
 * --------------------------------
 * This command uses Spatie's TenantAware trait to automatically run for all tenants.
 * The trait intercepts execute() and loops through tenants, calling handle() for each.
 *
 * How to make any command tenant-aware:
 * 1. Add `use TenantAware;` trait
 * 2. Add `{--tenant=*}` to the signature
 * 3. Schedule normally: $schedule->command('my:command')->daily();
 *    It will auto-run for ALL active tenants.
 * 4. To run for specific tenant(s): php artisan my:command --tenant=1 --tenant=2
 *
 * Alternative approaches (not used here):
 * - Option B: $schedule->command('tenants:artisan', ['artisanCommand' => 'capital:snapshot'])
 * - Option C: Manual loop with $tenant->execute(fn() => ...) inside a scheduled closure
 */
class TakeCapitalSnapshot extends Command
{
    use TenantAware;

    protected $signature = 'capital:snapshot {--tenant=* : Tenant ID(s), defaults to all tenants} {--month= : Month (1-12), defaults to previous month} {--year= : Year, defaults to previous month\'s year}';

    protected $description = 'Take a snapshot of the capital report for end-of-month tracking (runs per tenant)';

    public function handle(): int
    {
        $previousMonth = now()->subMonthNoOverflow();
        $month = (int) ($this->option('month') ?? $previousMonth->month);
        $year = (int) ($this->option('year') ?? $previousMonth->year);

        $this->info("Taking capital snapshot for {$year}-{$month}...");

        try {
            $controller = app(CapitalReportController::class);
            $report = $controller->calculateCapitalReport();

            CapitalSnapshot::updateOrCreate(
                ['year' => $year, 'month' => $month],
                [
                    'available_stock_value' => $report['available_stock_value'],
                    'vat_on_current_stock' => $report['vat_on_current_stock'],
                    'pending_purchases_value' => $report['pending_purchases_value'],
                    'net_stock_value' => $report['net_stock_value'],
                    'unpaid_customer_balance' => $report['unpaid_customer_balance'],
                    'unapproved_payments' => $report['unapproved_payment_orders'],
                    'accounts_balance' => $report['money_in_all_accounts'],
                    'net_capital' => $report['net_capital'],
                    'debt_account' => $report['debt_account'],
                    'final_result' => $report['final_result'],
                ]
            );

            $this->info("Snapshot saved successfully!");
            $this->line("Net Capital: \${$report['net_capital']}");
            $this->line("Final Result: \${$report['final_result']}");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to take capital snapshot: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
