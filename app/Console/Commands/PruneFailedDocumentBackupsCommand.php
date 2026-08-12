<?php

namespace App\Console\Commands;

use App\Models\DocumentBackup;
use App\Models\Setting;
use App\Models\Tenant;
use Illuminate\Console\Command;

class PruneFailedDocumentBackupsCommand extends Command
{
    protected $signature   = 'backup:documents-prune-failed';
    protected $description = 'Delete stale FAILED document-backup ledger rows older than the configured retention (default 365 days). Successful rows are never pruned.';

    public function handle(): int
    {
        $this->info('Pruning stale failed document-backup rows...');

        Tenant::runForEachActive('Prune failed document backups', function (Tenant $tenant) {
            // Setting read here runs in the tenant context (retention is per-tenant configurable).
            $days   = (int) Setting::get('backup', 'document_failed_retention_days', 365, false, Setting::TYPE_NUMBER);
            $cutoff = now()->subDays($days);

            $deleted = DocumentBackup::where('status', DocumentBackup::STATUS_FAILED)
                ->where('updated_at', '<', $cutoff)
                ->delete();

            $this->info("  ✓ {$tenant->tenant_key}: pruned {$deleted} failed rows older than {$days} days");
        });

        $this->info('Prune complete.');
        return self::SUCCESS;
    }
}
