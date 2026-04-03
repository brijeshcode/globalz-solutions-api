<?php

namespace App\Console\Commands;

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

        $this->info("Starting backup for {$tenants->count()} tenant(s)...");

        foreach ($tenants as $tenant) {
            $this->info("Backing up tenant: {$tenant->tenant_key}");

            try {
                $tenant->makeCurrent();
                $log = $backupService->run($tenant);

                if ($log->status === \App\Models\BackupLog::STATUS_SUCCESS) {
                    $this->info("  ✓ Success — {$log->file_name} ({$this->formatBytes($log->file_size)}) in {$log->duration_seconds}s");
                    $storageService->pushToRemote($tenant, $log);
                } else {
                    $this->error("  ✗ Failed — {$log->error_message}");
                }
            } catch (\Throwable $e) {
                $this->error("  ✗ Exception for tenant {$tenant->tenant_key}: {$e->getMessage()}");
            }
        }

        $this->info('Backup run complete.');
        return self::SUCCESS;
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
