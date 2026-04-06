<?php

namespace App\Console\Commands;

use App\Helpers\FeatureHelper;
use App\Models\MirrorLog;
use App\Models\Setting;
use App\Models\Tenant;
use App\Services\Mirror\DatabaseMirrorService;
use Illuminate\Console\Command;

class MirrorAllTenantsCommand extends Command
{
    protected $signature   = 'mirror:all-tenants';
    protected $description = 'Mirror database to remote MySQL for every qualifying active tenant (runs synchronously)';

    public function handle(DatabaseMirrorService $mirrorService): int
    {
        $tenants = Tenant::on('mysql')->where('is_active', true)->get();

        if ($tenants->isEmpty()) {
            $this->info('No active tenants found.');
            return self::SUCCESS;
        }

        $this->info("Checking {$tenants->count()} tenant(s) for mirror...");

        foreach ($tenants as $tenant) {
            try {
                $tenant->makeCurrent();

                if (!FeatureHelper::isDatabaseMirror()) {
                    FeatureHelper::flush();
                    Tenant::forgetCurrent();
                    continue;
                }

                if (!Setting::get('mirror', 'enabled', false, false, Setting::TYPE_BOOLEAN)) {
                    FeatureHelper::flush();
                    Tenant::forgetCurrent();
                    continue;
                }

                $this->info("Mirroring tenant: {$tenant->tenant_key}");

                $log = $mirrorService->run($tenant);

                if ($log === null) {
                    $this->info("  → Skipped (no changes detected)");
                } elseif ($log->status === MirrorLog::STATUS_SUCCESS) {
                    $this->info("  ✓ Success in {$log->duration_seconds}s");
                } else {
                    $this->error("  ✗ Failed — {$log->error_message}");
                }
            } catch (\Throwable $e) {
                $this->error("  ✗ Exception for tenant {$tenant->tenant_key}: {$e->getMessage()}");
            } finally {
                FeatureHelper::flush();
                Tenant::forgetCurrent();
            }
        }

        $this->info('Mirror run complete.');
        return self::SUCCESS;
    }
}
