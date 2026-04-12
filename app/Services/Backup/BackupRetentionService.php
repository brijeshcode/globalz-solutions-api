<?php

namespace App\Services\Backup;

use App\Models\BackupLog;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

class BackupRetentionService
{
    /**
     * Run retention cleanup for one tenant.
     * Reads per-tenant settings:
     *   backup.retention_type  — 'by_count' (default) or 'by_days'
     *   backup.retention_value — number of backups to keep (default 60) or days
     * Tenant must already be current when this is called.
     */
    public function runForTenant(int $tenantId): void
    {
        $type  = Setting::get('backup', 'retention_type',  'by_count', false, Setting::TYPE_STRING);
        $value = (int) Setting::get('backup', 'retention_value', 60,   false, Setting::TYPE_NUMBER);

        if ($type === 'by_days') {
            $this->pruneByDays($tenantId, $value);
        } else {
            $this->pruneByCount($tenantId, $value);
        }
    }

    /**
     * Keep only the N most recent successful backups; delete the rest.
     */
    protected function pruneByCount(int $tenantId, int $keep): void
    {
        $keepIds = BackupLog::on('mysql')
            ->forTenant($tenantId)
            ->successful()
            ->orderByDesc('created_at')
            ->limit($keep)
            ->pluck('id');

        $toDelete = BackupLog::on('mysql')
            ->forTenant($tenantId)
            ->successful()
            ->whereNotIn('id', $keepIds)
            ->get();

        foreach ($toDelete as $log) {
            $this->deleteBackup($log);
        }
    }

    /**
     * Delete successful backups older than $days days.
     */
    protected function pruneByDays(int $tenantId, int $days): void
    {
        $cutoff = now()->subDays($days);

        $toDelete = BackupLog::on('mysql')
            ->forTenant($tenantId)
            ->successful()
            ->where('created_at', '<', $cutoff)
            ->get();

        foreach ($toDelete as $log) {
            $this->deleteBackup($log);
        }
    }

    /**
     * Delete the physical backup file and the log record.
     */
    protected function deleteBackup(BackupLog $log): void
    {
        if (Storage::disk('backup')->exists($log->file_path)) {
            Storage::disk('backup')->delete($log->file_path);
        }

        $log->delete();
    }
}
