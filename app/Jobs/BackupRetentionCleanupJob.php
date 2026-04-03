<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\Backup\BackupRetentionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class BackupRetentionCleanupJob implements ShouldQueue
{
    use Queueable;

    public $tries = 1;

    public function handle(BackupRetentionService $retentionService): void
    {
        $tenants = Tenant::on('mysql')->where('is_active', true)->get();

        foreach ($tenants as $tenant) {
            try {
                $retentionService->runForTenant($tenant->id);
            } catch (\Throwable $e) {
                Log::error("BackupRetentionCleanupJob failed for tenant #{$tenant->id}", [
                    'error' => $e->getMessage(),
                ]);
                // Continue with other tenants even if one fails
            }
        }
    }
}
