<?php

namespace App\Console\Commands;

use App\Models\BackupLog;
use App\Models\Setting;
use App\Models\Tenant;
use App\Services\Backup\BackupService;
use App\Services\Backup\BackupStorageService;
use Illuminate\Console\Command;

class BackupAllTenantsCommand extends Command
{
    protected $signature   = 'backup:all-tenants';
    protected $description = 'Run database backup for every active tenant (runs synchronously — no queue worker needed)';

    public function handle(BackupService $backupService, BackupStorageService $storageService): int
    {
        $this->info('Starting backup check...');

        Tenant::runForEachActive('Tenant backup', function (Tenant $tenant) use ($backupService, $storageService) {
            $skip = $this->skipReason($tenant, $backupService);

            if ($skip !== null) {
                $this->info("  ↷ Skipped {$tenant->tenant_key}: {$skip}");
                return ['skipped' => $skip];
            }

            $this->info("Backing up tenant: {$tenant->tenant_key}");

            $log = $backupService->run($tenant);

            if ($log->status !== BackupLog::STATUS_SUCCESS) {
                $this->error("  ✗ Failed — {$log->error_message}");
                return ['failed' => $log->error_message];
            }

            $this->info("  ✓ Success — {$log->file_name} ({$this->formatBytes($log->file_size)}) in {$log->duration_seconds}s");
            $storageService->pushToRemote($tenant, $log);

            return ['file' => $log->file_name, 'size' => $log->file_size, 'duration_seconds' => $log->duration_seconds];
        });

        $this->info('Backup run complete.');
        return self::SUCCESS;
    }

    /**
     * Returns a skip reason string if the backup should not run for this tenant,
     * or null if the backup should proceed.
     * Tenant must already be current when this is called.
     */
    private function skipReason(Tenant $tenant, BackupService $backupService): ?string
    {
        $frequencyHours  = (int)  Setting::get('backup', 'frequency_hours',  24,   false, Setting::TYPE_NUMBER);
        $preferredHour   = (int)  Setting::get('backup', 'preferred_hour',   2,    false, Setting::TYPE_NUMBER);
        $skipIfUnchanged = (bool) Setting::get('backup', 'skip_if_unchanged', true, false, Setting::TYPE_BOOLEAN);

        $lastBackup = BackupLog::on('mysql')
            ->forTenant($tenant->id)
            ->successful()
            ->latest()
            ->first();

        // For daily-or-less-frequent schedules, only run at the preferred hour
        if ($frequencyHours >= 24 && now()->hour !== $preferredHour) {
            return "not preferred hour ({$preferredHour}:00)";
        }

        // Check that enough time has elapsed since the last backup.
        // Use a 5-minute grace window to absorb cron scheduling variance — without it,
        // a cron that fires even 1 second late produces 59m59s elapsed which truncates
        // to 59 minutes, causing a skip and doubling the effective interval.
        if ($lastBackup) {
            $elapsedMinutes = $lastBackup->created_at->diffInMinutes(now());
            $thresholdMinutes = ($frequencyHours * 60) - 5;
            if ($elapsedMinutes < $thresholdMinutes) {
                $elapsedHours = round($elapsedMinutes / 60, 1);
                return "frequency not reached ({$elapsedHours}h elapsed, need {$frequencyHours}h)";
            }
        }

        // Skip if no data has changed since the last backup
        if ($skipIfUnchanged && $lastBackup) {
            if (!$backupService->hasDataChangedSince($tenant, $lastBackup->created_at)) {
                return 'no data changes since last backup';
            }
        }

        return null;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
