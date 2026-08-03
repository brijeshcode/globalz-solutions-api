<?php

namespace App\Services\Backup;

use App\Models\BackupLog;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

class BackupRetentionService
{
    /**
     * Known disk types. Retention can be configured independently for each.
     */
    public const KNOWN_DISKS = ['local', 's3', 'ftp', 'dropbox'];

    /**
     * Absolute minimum backups to retain per disk, regardless of retention settings.
     * This prevents accidental total loss even when pruning is misconfigured.
     */
    public const MIN_KEEP = 10;

    /**
     * Run retention cleanup for one tenant, per disk.
     * Each disk reads its own settings:
     *   backup.retention_{disk}_enabled — true (default) or false (skip this disk)
     *   backup.retention_{disk}_type    — 'by_count' or 'by_days'; falls back to global retention_type
     *   backup.retention_{disk}_value   — count or days; falls back to global retention_value
     *
     * Global fallback settings:
     *   backup.retention_type  — 'by_count' (default) or 'by_days'
     *   backup.retention_value — 60 (default)
     *
     * Tenant must already be current when this is called.
     */
    public function runForTenant(int $tenantId): void
    {
        $globalType  = Setting::get('backup', 'retention_type',  'by_count', false, Setting::TYPE_STRING);
        $globalValue = (int) Setting::get('backup', 'retention_value', 60, false, Setting::TYPE_NUMBER);

        $disks = BackupLog::on('mysql')
            ->forTenant($tenantId)
            ->successful()
            ->distinct()
            ->pluck('disk');

        foreach ($disks as $disk) {
            // Default is FALSE — pruning must be explicitly enabled per disk to avoid accidental deletion.
            $enabled = (bool) Setting::get('backup', "retention_{$disk}_enabled", false, false, Setting::TYPE_BOOLEAN);

            if (!$enabled) {
                continue;
            }

            $rawType  = Setting::get('backup', "retention_{$disk}_type",  null, false, Setting::TYPE_STRING);
            $rawValue = Setting::get('backup', "retention_{$disk}_value", null, false, Setting::TYPE_NUMBER);

            $type  = $rawType  ?? $globalType;
            $value = $rawValue !== null ? (int) $rawValue : $globalValue;

            if ($type === 'by_days') {
                $this->pruneByDays($tenantId, $disk, $value);
            } else {
                $this->pruneByCount($tenantId, $disk, $value);
            }
        }
    }

    /**
     * Keep only the N most recent successful backups for the given disk; delete the rest.
     * Always retains at least MIN_KEEP backups regardless of the configured value.
     */
    protected function pruneByCount(int $tenantId, string $disk, int $keep): void
    {
        $keep = max($keep, self::MIN_KEEP);

        $keepIds = BackupLog::on('mysql')
            ->forTenant($tenantId)
            ->successful()
            ->where('disk', $disk)
            ->orderByDesc('created_at')
            ->limit($keep)
            ->pluck('id');

        $toDelete = BackupLog::on('mysql')
            ->forTenant($tenantId)
            ->successful()
            ->where('disk', $disk)
            ->whereNotIn('id', $keepIds)
            ->get();

        foreach ($toDelete as $log) {
            $this->deleteBackup($log);
        }
    }

    /**
     * Delete successful backups older than $days days for the given disk.
     * Always retains at least MIN_KEEP most recent backups regardless of the cutoff date.
     */
    protected function pruneByDays(int $tenantId, string $disk, int $days): void
    {
        $cutoff = now()->subDays($days);

        $safeIds = BackupLog::on('mysql')
            ->forTenant($tenantId)
            ->successful()
            ->where('disk', $disk)
            ->orderByDesc('created_at')
            ->limit(self::MIN_KEEP)
            ->pluck('id');

        $toDelete = BackupLog::on('mysql')
            ->forTenant($tenantId)
            ->successful()
            ->where('disk', $disk)
            ->where('created_at', '<', $cutoff)
            ->whereNotIn('id', $safeIds)
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
