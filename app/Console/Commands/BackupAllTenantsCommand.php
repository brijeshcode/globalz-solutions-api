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
        $tenants = Tenant::on('mysql')->where('is_active', true)->get();

        if ($tenants->isEmpty()) {
            $this->info('No active tenants found.');
            return self::SUCCESS;
        }

        $this->info("Starting backup check for {$tenants->count()} tenant(s)...");

        foreach ($tenants as $tenant) {
            try {
                $tenant->makeCurrent();

                $skip = $this->skipReason($tenant, $backupService);

                if ($skip !== null) {
                    $this->info("  ↷ Skipped {$tenant->tenant_key}: {$skip}");
                    continue;
                }

                $this->info("Backing up tenant: {$tenant->tenant_key}");

                $log = $backupService->run($tenant);

                if ($log->status === BackupLog::STATUS_SUCCESS) {
                    $this->info("  ✓ Success — {$log->file_name} ({$this->formatBytes($log->file_size)}) in {$log->duration_seconds}s");
                    $storageService->pushToRemote($tenant, $log);
                } else {
                    $this->error("  ✗ Failed — {$log->error_message}");
                }
            } catch (\Throwable $e) {
                $this->error("  ✗ Exception for tenant {$tenant->tenant_key}: {$e->getMessage()}");
            } finally {
                Tenant::forgetCurrent();
            }
        }

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

        // Check that enough time has elapsed since the last backup
        if ($lastBackup) {
            $elapsed = (int) $lastBackup->created_at->diffInHours(now());
            if ($elapsed < $frequencyHours) {
                return "frequency not reached ({$elapsed}h elapsed, need {$frequencyHours}h)";
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
