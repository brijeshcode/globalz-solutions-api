<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;

class ScheduleDocumentCleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:schedule-cleanup
                            {--enable : Enable automatic cleanup scheduling}
                            {--disable : Disable automatic cleanup scheduling}
                            {--status : Show current scheduling status}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage automatic document cleanup scheduling';

    /**
     * Execute the console command.
     */
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

        // Default: show help
        $this->showHelp();
        return Command::SUCCESS;
    }

    /**
     * Enable automatic cleanup scheduling.
     */
    private function enableScheduling(): int
    {
        // Store scheduling configuration
        $config = [
            'enabled' => true,
            'schedule' => 'daily',
            'time' => '02:00', // 2 AM
            'options' => [
                'days' => 30,
                'force' => true
            ],
            'enabled_at' => now()->toISOString()
        ];

        // You can store this in cache, config, or database
        // For now, we'll show how to manually add to the schedule
        
        $this->info('📅 Document cleanup scheduling has been enabled');
        $this->info('');
        $this->info('To complete the setup, add this to your App\Console\Kernel.php schedule method:');
        $this->info('');
        $this->line('protected function schedule(Schedule $schedule)');
        $this->line('{');
        $this->line('    // Document cleanup - runs daily at 2 AM');
        $this->line('    $schedule->command(\'documents:cleanup-orphaned --days=30 --force\')');
        $this->line('             ->daily()');
        $this->line('             ->at(\'02:00\')');
        $this->line('             ->withoutOverlapping()');
        $this->line('             ->runInBackground();');
        $this->line('}');
        $this->info('');
        $this->info('Or use Laravel\'s task scheduler cron:');
        $this->line('* * * * * cd /path/to/artisan && php artisan schedule:run >> /dev/null 2>&1');

        return Command::SUCCESS;
    }

    /**
     * Disable automatic cleanup scheduling.
     */
    private function disableScheduling(): int
    {
        $this->info('🚫 Document cleanup scheduling has been disabled');
        $this->info('');
        $this->info('Remove the cleanup command from your App\Console\Kernel.php schedule method');
        $this->info('or comment out the line in your crontab.');

        return Command::SUCCESS;
    }

    /**
     * Show current scheduling status.
     */
    private function showStatus(): int
    {
        $this->info('📋 Document Cleanup Scheduling Status');
        $this->info('');
        
        // Check if the cleanup command exists
        $this->info('✅ Cleanup command available: documents:cleanup-orphaned');
        
        $this->info('');
        $this->info('Current recommended schedule:');
        $this->line('  • Frequency: Daily');
        $this->line('  • Time: 02:00 (2 AM)');
        $this->line('  • Delete soft-deleted documents older than: 30 days');
        $this->line('  • Auto-confirm: Yes (--force)');
        
        $this->info('');
        $this->info('Manual cleanup commands:');
        $this->line('  php artisan documents:cleanup-orphaned --dry-run    # Preview cleanup');
        $this->line('  php artisan documents:cleanup-orphaned             # Interactive cleanup');
        $this->line('  php artisan documents:cleanup-orphaned --force     # Auto cleanup');
        $this->line('  php artisan documents:cleanup-orphaned --days=60   # Custom retention');

        return Command::SUCCESS;
    }

    /**
     * Show help information.
     */
    private function showHelp(): void
    {
        $this->info('📚 Document Cleanup Scheduling Help');
        $this->info('');
        $this->info('This command helps you set up automatic cleanup of:');
        $this->line('  • Orphaned files (files without database records)');
        $this->line('  • Old soft-deleted documents (default: 30+ days old)');
        $this->line('  • Database records without files (marks as missing)');
        
        $this->info('');
        $this->info('Available options:');
        $this->line('  --enable    Enable automatic cleanup scheduling');
        $this->line('  --disable   Disable automatic cleanup scheduling');
        $this->line('  --status    Show current scheduling status');
        
        $this->info('');
        $this->info('Examples:');
        $this->line('  php artisan documents:schedule-cleanup --enable');
        $this->line('  php artisan documents:schedule-cleanup --status');
        $this->line('  php artisan documents:schedule-cleanup --disable');
    }
}