<?php

namespace App\Services\Backup;

use App\Models\BackupLog;
use App\Models\Tenant;
use Carbon\Carbon;
use Ifsnop\Mysqldump\Mysqldump;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BackupService
{
    /**
     * Generate the filename for a tenant backup.
     * Format: {tenant_key}-YYYY-MM-DD-HH-mm-ss.sql.gz
     */
    public function generateFileName(Tenant $tenant): string
    {
        return $tenant->tenant_key . '-' . now()->format('Y-m-d-H-i-s') . '.sql.gz';
    }

    /**
     * Generate the relative storage path for a tenant backup.
     * Format: database/{tenant_key}/{year}/{month}/{filename}
     */
    public function generateFilePath(Tenant $tenant): string
    {
        return sprintf(
            'database/%s/%s/%s',
            $tenant->tenant_key,
            now()->format('Y/m'),
            $this->generateFileName($tenant)
        );
    }

    /**
     * Run a full backup for the given tenant.
     * Uses pure PHP mysqldump — no shell_exec or system calls needed.
     * Returns the BackupLog record (status: success or failed).
     */
    public function run(Tenant $tenant, ?int $triggeredBy = null): BackupLog
    {
        $fileName = $this->generateFileName($tenant);
        $filePath = sprintf(
            'database/%s/%s/%s',
            $tenant->tenant_key,
            now()->format('Y/m'),
            $fileName
        );

        $dbConfig = $tenant->getDatabaseConfig();

        $log = BackupLog::create([
            'tenant_id'     => $tenant->id,
            'tenant_key'    => $tenant->tenant_key,
            'database_name' => $dbConfig['database'],
            'file_name'     => $fileName,
            'file_path'     => $filePath,
            'disk'          => 'local',
            'status'        => BackupLog::STATUS_PENDING,
            'tier'          => BackupLog::TIER_DAILY,
            'compression'   => 'gzip',
            'triggered_by'  => $triggeredBy,
        ]);

        $log->update(['status' => BackupLog::STATUS_RUNNING]);

        $startTime   = microtime(true);
        $tempGzPath  = storage_path('app/backups/_tmp_' . $log->id . '.sql.gz');

        try {
            $this->ensureTempDirectory();
            $this->dumpDatabase($dbConfig, $tempGzPath);
            $this->storeOnDisk($tempGzPath, $filePath);

            $log->update([
                'status'           => BackupLog::STATUS_SUCCESS,
                'file_size'        => Storage::disk('backup')->size($filePath),
                'duration_seconds' => (int) round(microtime(true) - $startTime),
            ]);
        } catch (\Throwable $e) {
            $log->update([
                'status'           => BackupLog::STATUS_FAILED,
                'error_message'    => $e->getMessage(),
                'duration_seconds' => (int) round(microtime(true) - $startTime),
            ]);
        } finally {
            if (file_exists($tempGzPath)) {
                unlink($tempGzPath);
            }
        }

        return $log->fresh();
    }

    /**
     * Dump the tenant database directly to a gzipped file using pure PHP.
     * No shell_exec, no proc_open — works on shared hosting.
     */
    protected function dumpDatabase(array $dbConfig, string $outputPath): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s',
            $dbConfig['host'],
            $dbConfig['port'] ?? 3306,
            $dbConfig['database']
        );

        $dump = new Mysqldump($dsn, $dbConfig['username'], $dbConfig['password'], [
            'compress'          => Mysqldump::GZIP,
            'single-transaction'=> true,
            'add-drop-table'    => true,
            'skip-triggers'     => false,
            'add-locks'         => true,
        ]);

        $dump->start($outputPath);
    }

    /**
     * Move the temp gzipped file to the private backup disk.
     */
    protected function storeOnDisk(string $tempGzPath, string $storagePath): void
    {
        $directory = dirname($storagePath);
        Storage::disk('backup')->makeDirectory($directory);

        $stream = fopen($tempGzPath, 'rb');
        Storage::disk('backup')->writeStream($storagePath, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    /**
     * Check if any data in the tenant database has changed since the given timestamp.
     * Queries all tables that have an updated_at column and returns true if any record
     * is newer than $since. Returns true when there is no previous backup (first run).
     */
    public function hasDataChangedSince(Tenant $tenant, Carbon $since): bool
    {
        $dbName = $tenant->getDatabaseConfig()['database'];

        $tables = DB::select(
            'SELECT DISTINCT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND COLUMN_NAME = ?',
            [$dbName, 'updated_at']
        );

        foreach ($tables as $table) {
            if (DB::table($table->TABLE_NAME)->where('updated_at', '>', $since)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ensure the temp directory exists for writing dumps.
     */
    protected function ensureTempDirectory(): void
    {
        $dir = storage_path('app/backups');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
