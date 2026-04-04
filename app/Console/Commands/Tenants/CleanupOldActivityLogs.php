<?php

namespace App\Console\Commands\Tenants;

use App\Models\ActivityLog\ActivityLog;
use App\Models\Tenant;
use Illuminate\Console\Command;

class CleanupOldActivityLogs extends Command
{
    protected $signature = 'activitylog:cleanup
                            {--days= : Number of days to retain (overrides config)}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Clean up old activity logs based on retention period (runs per tenant)';

    public function handle()
    {
        $retentionDays = $this->option('days') ?? config('activitylog.retention_days');

        if (!$retentionDays || $retentionDays <= 0) {
            $this->info('Activity log cleanup is disabled (retention_days is not set or is 0).');
            $this->info('Set ACTIVITY_LOG_RETENTION_DAYS in your .env file or use --days option.');
            return 0;
        }

        $tenants = Tenant::on('mysql')->where('is_active', true)->get();

        if ($tenants->isEmpty()) {
            $this->info('No active tenants found.');
            return 0;
        }

        $cutoffDate = now()->subDays($retentionDays);
        $this->info("Cleaning logs older than {$retentionDays} days (before {$cutoffDate->format('Y-m-d H:i:s')}) across {$tenants->count()} tenant(s)...");

        foreach ($tenants as $tenant) {
            $this->info("Processing tenant: {$tenant->tenant_key}");

            try {
                $tenant->makeCurrent();

                $count = ActivityLog::where('timestamp', '<', $cutoffDate)->count();

                if ($count === 0) {
                    $this->info('  No old logs found.');
                    continue;
                }

                $this->info("  Found {$count} logs to delete.");

                if (!$this->option('force') && !$this->confirm("  Delete {$count} logs for {$tenant->tenant_key}?")) {
                    $this->info('  Skipped.');
                    continue;
                }

                $deleted = 0;
                ActivityLog::where('timestamp', '<', $cutoffDate)
                    ->chunkById(100, function ($logs) use (&$deleted) {
                        foreach ($logs as $log) {
                            $log->details()->delete();
                            $log->delete();
                            $deleted++;
                        }
                    });

                $this->info("  ✓ Deleted {$deleted} logs.");
            } catch (\Throwable $e) {
                $this->error("  ✗ Failed for tenant {$tenant->tenant_key}: {$e->getMessage()}");
            } finally {
                Tenant::forgetCurrent();
            }
        }

        return 0;
    }
}
