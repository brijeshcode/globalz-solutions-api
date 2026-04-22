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
        $tenants = Tenant::on('mysql')->where('is_active', true)->get();

        if ($tenants->isEmpty()) {
            $this->info('No active tenants found.');
            return self::SUCCESS;
        }

        $this->info("Dispatching mirror jobs for {$tenants->count()} tenant(s)...");

        $dispatched = 0;

        foreach ($tenants as $tenant) {
            $tenant->makeCurrent();

            if (!FeatureHelper::isDatabaseMirror()) {
                FeatureHelper::flush();
                Tenant::forgetCurrent();
                continue;
            }

            FeatureHelper::flush();
            Tenant::forgetCurrent();

            MirrorTenantJob::dispatch($tenant->id);
            $this->info("  → Queued: {$tenant->tenant_key}");
            $dispatched++;
        }

        $this->info("Done — {$dispatched} job(s) dispatched.");
        return self::SUCCESS;
    }
}
