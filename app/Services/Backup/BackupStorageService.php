<?php

namespace App\Services\Backup;

use App\Models\BackupLog;
use App\Models\Setting;
use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;

class BackupStorageService
{
    /**
     * After local backup is stored, push to any remote drivers configured
     * for this tenant. Creates additional BackupLog records per remote disk.
     */
    public function pushToRemote(Tenant $tenant, BackupLog $localLog): void
    {
        $drivers = $this->getConfiguredRemoteDrivers($tenant);

        foreach ($drivers as $driver) {
            $this->uploadToDriver($tenant, $localLog, $driver);
        }
    }

    /**
     * Returns the list of remote drivers the tenant has configured.
     * Local is always present and never returned here (handled by BackupService).
     */
    public function getConfiguredRemoteDrivers(Tenant $tenant): array
    {
        $tenant->makeCurrent();

        $drivers = Setting::get('backup', 'storage_drivers', ['local'], false, Setting::TYPE_JSON);

        return array_values(array_filter((array) $drivers, fn($d) => $d !== 'local'));
    }

    /**
     * Upload the backup file to a specific remote driver.
     * Creates a new BackupLog record for this disk.
     */
    protected function uploadToDriver(Tenant $tenant, BackupLog $localLog, string $driver): void
    {
        try {
            $remoteDisk = $this->buildDisk($tenant, $driver);
            $contents   = Storage::disk('backup')->readStream($localLog->file_path);

            if (!$contents) {
                throw new \RuntimeException("Could not read local backup file: {$localLog->file_path}");
            }

            $remoteDisk->writeStream($localLog->file_path, $contents);
            if (is_resource($contents)) {
                fclose($contents);
            }

            BackupLog::create([
                'tenant_id'        => $localLog->tenant_id,
                'tenant_key'       => $localLog->tenant_key,
                'database_name'    => $localLog->database_name,
                'file_name'        => $localLog->file_name,
                'file_path'        => $localLog->file_path,
                'file_size'        => $localLog->file_size,
                'disk'             => $driver,
                'status'           => BackupLog::STATUS_SUCCESS,
                'tier'             => $localLog->tier,
                'compression'      => $localLog->compression,
                'duration_seconds' => 0,
                'triggered_by'     => $localLog->triggered_by,
            ]);
        } catch (\Throwable $e) {
            BackupLog::create([
                'tenant_id'     => $localLog->tenant_id,
                'tenant_key'    => $localLog->tenant_key,
                'database_name' => $localLog->database_name,
                'file_name'     => $localLog->file_name,
                'file_path'     => $localLog->file_path,
                'disk'          => $driver,
                'status'        => BackupLog::STATUS_FAILED,
                'tier'          => $localLog->tier,
                'compression'   => $localLog->compression,
                'error_message' => $e->getMessage(),
                'triggered_by'  => $localLog->triggered_by,
            ]);
        }
    }

    /**
     * Build an on-the-fly filesystem disk from tenant settings.
     */
    protected function buildDisk(Tenant $tenant, string $driver): \Illuminate\Contracts\Filesystem\Filesystem
    {
        $tenant->makeCurrent();

        $config = match ($driver) {
            's3' => [
                'driver'   => 's3',
                'key'      => Setting::get('backup', 's3_key'),
                'secret'   => Setting::get('backup', 's3_secret'),
                'region'   => Setting::get('backup', 's3_region', 'us-east-1'),
                'bucket'   => Setting::get('backup', 's3_bucket'),
                'url'      => null,
                'endpoint' => null,
                'throw'    => true,
            ],
            'ftp' => [
                'driver'                 => 'ftp',
                'host'                   => Setting::get('backup', 'ftp_host'),
                'username'               => Setting::get('backup', 'ftp_user'),
                'password'               => Setting::get('backup', 'ftp_password'),
                'port'                   => (int) Setting::get('backup', 'ftp_port', 21),
                'root'                   => Setting::get('backup', 'ftp_root', '/'),
                'passive'                => true,
                'ssl'                    => false,
                'timeout'                => 30,
                'recurseManually'        => true,
                'throw'                  => true,
            ],
            'dropbox' => [
                'driver'              => 'dropbox',
                'authorization_token' => Setting::get('backup', 'dropbox_token'),
                'throw'               => true,
            ],
            default => throw new \InvalidArgumentException("Unsupported backup driver: {$driver}"),
        };

        return Storage::build($config);
    }
}
