<?php

namespace App\Console\Commands;

use App\Services\Customers\CustomerBalanceService;
use Illuminate\Console\Command;

class CalculateYearlyClosingBalance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customers:calculate-yearly-closing {--year= : The year to calculate closing balance for (defaults to previous year)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate yearly closing balance for all customers at the end of the year';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Default to previous year if not specified
        $year = $this->option('year') ?? now()->subYear()->year;

        $this->info("Starting yearly closing balance calculation for year {$year}...");
        $this->info('This will aggregate all monthly balances and create yearly records.');
        $this->info('');

        try {
            $stats = CustomerBalanceService::calculateYearlyClosingForAllCustomers($year);

            $this->info("Yearly closing balance calculation completed for year {$year}!");
            $this->info('');
            $this->line("Total customers: {$stats['total']}");
            $this->line("Successfully processed: {$stats['processed']}");
            $this->line("Skipped (no data): {$stats['skipped']}");
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
            $this->error("Failed to calculate yearly closing balance for year {$year}: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
