<?php

namespace App\Console\Commands;

use App\Models\ActivityLog\ActivityLog;
use Illuminate\Console\Command;

class CleanupOldActivityLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'activitylog:cleanup
                            {--days= : Number of days to retain (overrides config)}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old activity logs based on retention period';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get retention days from option or config
        $retentionDays = $this->option('days') ?? config('activitylog.retention_days');

        // Check if cleanup is disabled
        if (!$retentionDays || $retentionDays <= 0) {
            $this->info('Activity log cleanup is disabled (retention_days is not set or is 0).');
            $this->info('Set ACTIVITY_LOG_RETENTION_DAYS in your .env file or use --days option.');
            return 0;
        }

        $cutoffDate = now()->subDays($retentionDays);

        // Count logs to be deleted
        $count = ActivityLog::where('timestamp', '<', $cutoffDate)->count();

        if ($count === 0) {
            $this->info('No activity logs found older than ' . $retentionDays . ' days.');
            return 0;
        }

        // Show info and ask for confirmation
        $this->info("Found {$count} activity logs older than {$retentionDays} days (before {$cutoffDate->format('Y-m-d H:i:s')}).");

        if (!$this->option('force')) {
            if (!$this->confirm('Do you want to delete these logs?')) {
                $this->info('Cleanup cancelled.');
                return 0;
            }
        }

        // Perform cleanup
        $this->info('Deleting old activity logs...');

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $deleted = 0;

        // Delete in chunks to avoid memory issues
        ActivityLog::where('timestamp', '<', $cutoffDate)
            ->chunkById(100, function ($logs) use (&$deleted, $bar) {
                foreach ($logs as $log) {
                    // Delete related details first (cascade)
                    $log->details()->delete();

                    // Delete the main log
                    $log->delete();

                    $deleted++;
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine();

        $this->info("✓ Successfully deleted {$deleted} activity logs.");

        return 0;
    }
}
