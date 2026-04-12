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
        $tenants = Tenant::on('mysql')->where('is_active', true)->get();

        if ($tenants->isEmpty()) {
            $this->info('No active tenants found.');
            return self::SUCCESS;
        }

        $this->info("Running retention cleanup for {$tenants->count()} tenant(s)...");

        foreach ($tenants as $tenant) {
            try {
                // Must be current so Setting::get() reads from the correct tenant DB
                $tenant->makeCurrent();
                $retentionService->runForTenant($tenant->id);
                $this->info("  ✓ Cleaned up: {$tenant->tenant_key}");
            } catch (\Throwable $e) {
                $this->error("  ✗ Failed for tenant {$tenant->tenant_key}: {$e->getMessage()}");
            } finally {
                Tenant::forgetCurrent();
            }
        }

        $this->info('Retention cleanup complete.');
        return self::SUCCESS;
    }
}
