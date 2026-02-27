<?php

namespace App\Console\Commands\Tenants;

use App\Services\Customers\CustomerBalanceService;
use Illuminate\Console\Command;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class CalculateMonthlyClosingBalance extends Command
{
    use TenantAware;

    protected $signature = 'customers:calculate-monthly-closing {--tenant=* : Tenant ID(s), defaults to all tenants}';

    protected $description = 'Calculate closing balance for all customers at the end of the month';

    public function handle(): int
    {
        $this->info('Starting monthly closing balance calculation...');
        $this->info('Processing current month only (scheduled task)');
        $this->info('');

        try {
            $stats = CustomerBalanceService::processMonthlyClosingForAllCustomers(null);

            $this->info('Monthly closing balance calculation completed!');
            $this->info('');
            $this->line("Total customers: {$stats['total']}");
            $this->line("Successfully processed: {$stats['processed']}");
            $this->line("Skipped: {$stats['skipped']}");
            $this->line("Errors: {$stats['errors']}");

            if ($stats['errors'] > 0) {
                $this->warn('');
                $this->warn('Some customers had errors. Check the logs for details.');

                if (!empty($stats['error_details'])) {
                    $this->warn('Error details:');
                    foreach ($stats['error_details'] as $error) {
                        $this->warn("  Customer {$error['customer_id']}: {$error['error']}");
                    }
                }
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to calculate monthly closing balance: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
