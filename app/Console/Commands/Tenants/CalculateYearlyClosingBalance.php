<?php

namespace App\Console\Commands\Tenants;

use App\Models\Tenant;
use App\Services\Customers\CustomerBalanceService;
use Illuminate\Console\Command;

class CalculateYearlyClosingBalance extends Command
{
    protected $signature = 'customers:calculate-yearly-closing {--tenant=* : Tenant ID(s), defaults to all tenants} {--year= : The year to calculate closing balance for (defaults to previous year)}';

    protected $description = 'Calculate yearly closing balance for all customers at the end of the year';

    public function handle(): int
    {
        $year = (int) ($this->option('year') ?? now()->subYear()->year);

        $this->info("Starting yearly closing balance calculation for year {$year}...");

        Tenant::runForEachActive('Yearly closing balance', function (Tenant $tenant) use ($year) {
            $stats = CustomerBalanceService::calculateYearlyClosingForAllCustomers($year);

            $this->info("  ✓ {$tenant->tenant_key} — processed {$stats['processed']}/{$stats['total']}, skipped {$stats['skipped']}, errors {$stats['errors']}");

            foreach ($stats['error_details'] ?? [] as $error) {
                $this->warn("    Customer {$error['customer_id']}: {$error['error']}");
            }

            return $stats + ['year' => $year];
        }, $this->option('tenant'));

        $this->info("Yearly closing balance calculation completed for year {$year}.");
        return Command::SUCCESS;
    }
}
