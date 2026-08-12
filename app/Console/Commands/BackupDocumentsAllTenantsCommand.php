<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Backup\BackupStorageService;
use App\Services\Backup\DocumentBackupService;
use Illuminate\Console\Command;

class BackupDocumentsAllTenantsCommand extends Command
{
    protected $signature   = 'backup:documents-all-tenants';
    protected $description = 'Incrementally push new document files to each tenant\'s configured remote drivers (runs synchronously — no queue worker needed)';

    public function handle(DocumentBackupService $service, BackupStorageService $storageService): int
    {
        $this->info('Starting document backup...');

        Tenant::runForEachActive('Tenant document backup', function (Tenant $tenant) use ($service, $storageService) {
            $drivers = $storageService->getConfiguredRemoteDrivers($tenant);

            if (empty($drivers)) {
                $this->info("  ↷ {$tenant->tenant_key}: no remote drivers configured");
                return ['skipped' => 'no remote drivers'];
            }

            foreach ($drivers as $disk) {
                $result = $service->run($tenant, $disk);

                if ($result['status'] === 'failed') {
                    $this->error("  ✗ {$tenant->tenant_key} [{$disk}]: run failed — {$result['error_message']}");
                    continue;
                }

                $this->info("  ✓ {$tenant->tenant_key} [{$disk}]: {$result['files_copied']} copied, {$result['files_skipped']} skipped, {$result['files_failed']} failed");
            }

            return ['drivers' => $drivers];
        });

        $this->info('Document backup complete.');
        return self::SUCCESS;
    }
}
