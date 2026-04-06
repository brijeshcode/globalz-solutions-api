<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\Mirror\DatabaseMirrorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class MirrorTenantJob implements ShouldQueue
{
    use Queueable;

    public $tries   = 2;
    public $backoff = [60, 300]; // retry after 1 min, then 5 min
    public $timeout = 900;       // 15 min max — large DBs can take a while

    public function __construct(
        protected int $tenantId,
        protected ?int $triggeredBy = null
    ) {}

    public function handle(DatabaseMirrorService $mirrorService): void
    {
        $tenant = Tenant::on('mysql')->find($this->tenantId);

        if (!$tenant || !$tenant->is_active) {
            Log::info("MirrorTenantJob: skipping tenant #{$this->tenantId} (not found or inactive)");
            return;
        }

        try {
            $tenant->makeCurrent();

            $log = $mirrorService->run($tenant, $this->triggeredBy);

            if ($log === null) {
                Log::info("MirrorTenantJob: skipped tenant #{$this->tenantId} (disabled or no changes)");
            } else {
                Log::info("MirrorTenantJob: tenant #{$this->tenantId} status={$log->status} duration={$log->duration_seconds}s");
            }
        } catch (\Throwable $e) {
            Log::error("MirrorTenantJob failed for tenant #{$this->tenantId}", [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            Tenant::forgetCurrent();
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("MirrorTenantJob permanently failed for tenant #{$this->tenantId}", [
            'error' => $exception->getMessage(),
        ]);
    }
}
