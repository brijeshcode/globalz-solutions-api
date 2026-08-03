<?php

namespace App\Console\Commands;

use App\Helpers\FeatureHelper;
use App\Jobs\MirrorTenantJob;
use App\Models\Tenant;
use Illuminate\Console\Command;

class MirrorAllTenantsCommand extends Command
{
    protected $signature   = 'mirror:all-tenants';
    protected $description = 'Dispatch mirror jobs for every qualifying active tenant';

    public function handle(): int
    {
        $this->info('Dispatching mirror jobs...');

        $dispatched = 0;

        Tenant::runForEachActive('Mirror dispatch', function (Tenant $tenant) use (&$dispatched) {
            $enabled = FeatureHelper::isDatabaseMirror();
            FeatureHelper::flush();

            if (!$enabled) {
                return ['skipped' => 'database mirror feature disabled'];
            }

            // MirrorTenantJob is NotTenantAware — it resolves the tenant itself from the id
            MirrorTenantJob::dispatch($tenant->id);
            $this->info("  → Queued: {$tenant->tenant_key}");
            $dispatched++;

            return ['dispatched' => true];
        });

        $this->info("Done — {$dispatched} job(s) dispatched.");
        return self::SUCCESS;
    }
}
