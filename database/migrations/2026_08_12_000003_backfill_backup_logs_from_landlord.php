<?php

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One-time data move: copy this tenant's rows from the landlord backup_logs
     * table into the tenant's own (new) backup_logs table.
     *
     * Runs per tenant via `tenants:artisan migrate`, where the tenant is current
     * and the default connection points at the tenant DB. Guarded so it is a no-op
     * on fresh installs / test runs (no current tenant, or no landlord source table).
     */
    public function up(): void
    {
        $tenant = Tenant::current();

        // No current tenant (fresh install / test migrate) or the landlord source is
        // already gone → nothing to backfill.
        if (!$tenant || !Schema::connection('mysql')->hasTable('backup_logs')) {
            return;
        }

        // Idempotent: never double-copy if this tenant already has rows.
        if (DB::table('backup_logs')->exists()) {
            return;
        }

        DB::connection('mysql')->table('backup_logs')
            ->where('tenant_id', $tenant->id)
            ->orderBy('id')
            ->chunk(200, function ($rows) {
                DB::table('backup_logs')->insert($rows->map(fn ($r) => (array) $r)->all());
            });
    }

    public function down(): void
    {
        // Data-only migration; the create/drop of the table is handled elsewhere.
    }
};
