<?php

namespace App\Console\Commands;

use App\Services\Customers\CustomerBalanceService;
use Illuminate\Console\Command;

class CalculateMonthlyClosingBalance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customers:calculate-monthly-closing';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate closing balance for all customers at the end of the month';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting monthly closing balance calculation...');
        $this->info('Processing current month only (scheduled task)');
        $this->info('');

        try {
            // Pass null to only process current month (this is the scheduled task)
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
