<?php

namespace App\Console\Commands\Tenants;

use App\Models\Tenant;
use App\Services\Customers\CustomerBalanceService;
use Illuminate\Console\Command;

class CalculateMonthlyClosingBalance extends Command
{
    protected $signature = 'customers:calculate-monthly-closing {--tenant=* : Tenant ID(s), defaults to all tenants}';

    protected $description = 'Calculate closing balance for all customers at the end of the month';

    public function handle(): int
    {
        $this->info('Starting monthly closing balance calculation (current month)...');

        Tenant::runForEachActive('Monthly closing balance', function (Tenant $tenant) {
            $stats = CustomerBalanceService::processMonthlyClosingForAllCustomers(null);

            $this->info("  ✓ {$tenant->tenant_key} — processed {$stats['processed']}/{$stats['total']}, skipped {$stats['skipped']}, errors {$stats['errors']}");

            foreach ($stats['error_details'] ?? [] as $error) {
                $this->warn("    Customer {$error['customer_id']}: {$error['error']}");
            }

            return $stats;
        }, $this->option('tenant'));

        $this->info('Monthly closing balance calculation completed.');
        return Command::SUCCESS;
    }
}
