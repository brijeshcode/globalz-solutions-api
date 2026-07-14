<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Backup\BackupRetentionService;
use Illuminate\Console\Command;

class BackupRetentionCleanupCommand extends Command
{
    protected $signature   = 'backup:retention-cleanup';
    protected $description = 'Run retention cleanup for all tenants (runs synchronously — no queue worker needed)';

    public function handle(BackupRetentionService $retentionService): int
    {
        $this->info('Running retention cleanup...');

        // Tenant must be current so Setting::get() reads from the correct tenant DB
        Tenant::runForEachActive('Backup retention cleanup', function (Tenant $tenant) use ($retentionService) {
            $retentionService->runForTenant($tenant->id);
            $this->info("  ✓ Cleaned up: {$tenant->tenant_key}");
        });

        $this->info('Retention cleanup complete.');
        return self::SUCCESS;
    }
}
