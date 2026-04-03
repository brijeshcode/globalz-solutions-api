<?php

namespace App\Services\Backup;

use App\Models\BackupLog;
use Illuminate\Support\Facades\Storage;

class BackupRetentionService
{
    /**
     * Run the full GFS retention cycle for one tenant.
     */
    public function runForTenant(int $tenantId): void
    {
        $this->promoteDailyToWeekly($tenantId);
        $this->promoteWeeklyToMonthly($tenantId);
        $this->promoteMonthlyToYearly($tenantId);
        // yearly → never deleted
    }

    /**
     * Daily backups older than 30 days:
     * keep the FIRST (oldest) backup of each ISO week → promote to weekly.
     * Delete all other daily backups in that week (file + record).
     */
    protected function promoteDailyToWeekly(int $tenantId): void
    {
        $cutoff = now()->subDays(30);

        $backups = BackupLog::forTenant($tenantId)
            ->byTier(BackupLog::TIER_DAILY)
            ->successful()
            ->where('created_at', '<', $cutoff)
            ->orderBy('created_at')
            ->get();

        // Group by ISO year+week (e.g. "202614")
        $byWeek = $backups->groupBy(fn($log) => $log->created_at->format('oW'));

        foreach ($byWeek as $logs) {
            $keep = $logs->first();
            $keep->update(['tier' => BackupLog::TIER_WEEKLY]);

            foreach ($logs->slice(1) as $log) {
                $this->deleteBackup($log);
            }
        }
    }

    /**
     * Weekly backups older than 26 weeks:
     * keep the FIRST of each calendar month → promote to monthly.
     * Delete all other weekly backups in that month.
     */
    protected function promoteWeeklyToMonthly(int $tenantId): void
    {
        $cutoff = now()->subWeeks(26);

        $backups = BackupLog::forTenant($tenantId)
            ->byTier(BackupLog::TIER_WEEKLY)
            ->successful()
            ->where('created_at', '<', $cutoff)
            ->orderBy('created_at')
            ->get();

        // Group by year+month (e.g. "202601")
        $byMonth = $backups->groupBy(fn($log) => $log->created_at->format('Ym'));

        foreach ($byMonth as $logs) {
            $keep = $logs->first();
            $keep->update(['tier' => BackupLog::TIER_MONTHLY]);

            foreach ($logs->slice(1) as $log) {
                $this->deleteBackup($log);
            }
        }
    }

    /**
     * Monthly backups older than 120 months (10 years):
     * keep the FIRST of each calendar year → promote to yearly.
     * Delete all other monthly backups in that year.
     */
    protected function promoteMonthlyToYearly(int $tenantId): void
    {
        $cutoff = now()->subMonths(120);

        $backups = BackupLog::forTenant($tenantId)
            ->byTier(BackupLog::TIER_MONTHLY)
            ->successful()
            ->where('created_at', '<', $cutoff)
            ->orderBy('created_at')
            ->get();

        // Group by year (e.g. "2016")
        $byYear = $backups->groupBy(fn($log) => $log->created_at->format('Y'));

        foreach ($byYear as $logs) {
            $keep = $logs->first();
            $keep->update(['tier' => BackupLog::TIER_YEARLY]);

            foreach ($logs->slice(1) as $log) {
                $this->deleteBackup($log);
            }
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
