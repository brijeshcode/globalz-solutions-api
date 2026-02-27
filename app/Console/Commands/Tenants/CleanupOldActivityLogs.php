<?php

namespace App\Console\Commands\Tenants;

use App\Models\ActivityLog\ActivityLog;
use Illuminate\Console\Command;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class CleanupOldActivityLogs extends Command
{
    use TenantAware;

    protected $signature = 'activitylog:cleanup
                            {--tenant=* : Tenant ID(s), defaults to all tenants}
                            {--days= : Number of days to retain (overrides config)}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Clean up old activity logs based on retention period';

    public function handle()
    {
        $retentionDays = $this->option('days') ?? config('activitylog.retention_days');

        if (!$retentionDays || $retentionDays <= 0) {
            $this->info('Activity log cleanup is disabled (retention_days is not set or is 0).');
            $this->info('Set ACTIVITY_LOG_RETENTION_DAYS in your .env file or use --days option.');
            return 0;
        }

        $cutoffDate = now()->subDays($retentionDays);

        $count = ActivityLog::where('timestamp', '<', $cutoffDate)->count();

        if ($count === 0) {
            $this->info('No activity logs found older than ' . $retentionDays . ' days.');
            return 0;
        }

        $this->info("Found {$count} activity logs older than {$retentionDays} days (before {$cutoffDate->format('Y-m-d H:i:s')}).");

        if (!$this->option('force')) {
            if (!$this->confirm('Do you want to delete these logs?')) {
                $this->info('Cleanup cancelled.');
                return 0;
            }
        }

        $this->info('Deleting old activity logs...');

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $deleted = 0;

        ActivityLog::where('timestamp', '<', $cutoffDate)
            ->chunkById(100, function ($logs) use (&$deleted, $bar) {
                foreach ($logs as $log) {
                    $log->details()->delete();
                    $log->delete();
                    $deleted++;
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine();

        $this->info("Successfully deleted {$deleted} activity logs.");

        return 0;
    }
}
