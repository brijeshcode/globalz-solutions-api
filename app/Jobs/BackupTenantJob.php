<?php

namespace App\Jobs;

use App\Models\BackupLog;
use App\Models\Tenant;
use App\Services\Backup\BackupService;
use App\Services\Backup\BackupStorageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class BackupTenantJob implements ShouldQueue
{
    use Queueable;

    public $tries   = 2;
    public $backoff = [60, 300]; // retry after 1 min, then 5 min

    public function __construct(
        protected int $tenantId,
        protected ?int $triggeredBy = null
    ) {}

    public function handle(BackupService $backupService, BackupStorageService $storageService): void
    {
        $tenant = Tenant::on('mysql')->find($this->tenantId);

        if (!$tenant || !$tenant->is_active) {
            Log::info("BackupTenantJob: skipping tenant #{$this->tenantId} (not found or inactive)");
            return;
        }

        try {
            $tenant->makeCurrent();

            $log = $backupService->run($tenant, $this->triggeredBy);

            if ($log->status === BackupLog::STATUS_SUCCESS) {
                $storageService->pushToRemote($tenant, $log);
            }
        } catch (\Throwable $e) {
            Log::error("BackupTenantJob failed for tenant #{$this->tenantId}", [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("BackupTenantJob permanently failed for tenant #{$this->tenantId}", [
            'error' => $exception->getMessage(),
        ]);
    }
}
