<?php

use App\Models\BackupLog;
use Illuminate\Support\Facades\DB;

uses()->group('api', 'backup');

it('persists backup logs to the tenant database', function () {
    $log = BackupLog::create([
        'tenant_id'     => $this->tenant->id,
        'tenant_key'    => $this->tenant->tenant_key,
        'database_name' => 'test_db',
        'file_name'     => 'x.sql.gz',
        'file_path'     => 'database/x.sql.gz',
        'disk'          => 'local',
        'status'        => BackupLog::STATUS_SUCCESS,
        'tier'          => BackupLog::TIER_DAILY,
    ]);

    // Resolves to the tenant connection (default when a tenant is current), not landlord.
    expect($log->getConnection()->getName())->toBe('tenant');
    expect(DB::connection('tenant')->table('backup_logs')->where('id', $log->id)->exists())->toBeTrue();

    // The forTenant + successful scopes still work against the tenant table.
    expect(BackupLog::query()->forTenant($this->tenant->id)->successful()->count())->toBe(1);
});
