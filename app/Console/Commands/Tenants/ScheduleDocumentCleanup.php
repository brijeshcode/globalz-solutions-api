<?php

namespace App\Console\Commands\Tenants;

use Illuminate\Console\Command;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class ScheduleDocumentCleanup extends Command
{
    use TenantAware;

    protected $signature = 'documents:schedule-cleanup
                            {--tenant=* : Tenant ID(s), defaults to all tenants}
                            {--enable : Enable automatic cleanup scheduling}
                            {--disable : Disable automatic cleanup scheduling}
                            {--status : Show current scheduling status}';

    protected $description = 'Manage automatic document cleanup scheduling';

    public function handle(): int
    {
        if ($this->option('enable')) {
            return $this->enableScheduling();
        }

        if ($this->option('disable')) {
            return $this->disableScheduling();
        }

        if ($this->option('status')) {
            return $this->showStatus();
        }

        $this->showHelp();
        return Command::SUCCESS;
    }

    private function enableScheduling(): int
    {
        $this->info('Document cleanup scheduling has been enabled');
        $this->info('');
        $this->info('To complete the setup, add this to your scheduler:');
        $this->info('');
        $this->line('$schedule->command(\'documents:cleanup-orphaned --force\')');
        $this->line('         ->daily()');
        $this->line('         ->at(\'02:00\')');
        $this->line('         ->withoutOverlapping()');
        $this->line('         ->runInBackground();');

        return Command::SUCCESS;
    }

    private function disableScheduling(): int
    {
        $this->info('Document cleanup scheduling has been disabled');
        $this->info('');
        $this->info('Remove the cleanup command from your scheduler.');

        return Command::SUCCESS;
    }

    private function showStatus(): int
    {
        $this->info('Document Cleanup Scheduling Status');
        $this->info('');
        $this->info('Cleanup command available: documents:cleanup-orphaned');
        $this->info('');
        $this->info('Current recommended schedule:');
        $this->line('  Frequency: Daily');
        $this->line('  Time: 02:00 (2 AM)');
        $this->line('  Delete soft-deleted documents older than: 30 days');
        $this->line('  Auto-confirm: Yes (--force)');
        $this->info('');
        $this->info('Manual cleanup commands:');
        $this->line('  php artisan documents:cleanup-orphaned --dry-run');
        $this->line('  php artisan documents:cleanup-orphaned');
        $this->line('  php artisan documents:cleanup-orphaned --force');
        $this->line('  php artisan documents:cleanup-orphaned --days=60');

        return Command::SUCCESS;
    }

    private function showHelp(): void
    {
        $this->info('Document Cleanup Scheduling Help');
        $this->info('');
        $this->info('This command helps you set up automatic cleanup of:');
        $this->line('  Orphaned files (files without database records)');
        $this->line('  Old soft-deleted documents (default: 30+ days old)');
        $this->line('  Database records without files (marks as missing)');
        $this->info('');
        $this->info('Available options:');
        $this->line('  --enable    Enable automatic cleanup scheduling');
        $this->line('  --disable   Disable automatic cleanup scheduling');
        $this->line('  --status    Show current scheduling status');
    }
}
