<?php

namespace App\Http\Controllers\Api\Backup;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\BackupLog;
use App\Services\Backup\BackupService;
use App\Services\Backup\BackupStorageService;
use App\Models\Setting;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function __construct()
    {
        if (!RoleHelper::canSuperAdmin()) {
            abort(403, 'Unauthorized');
        }
    }

    /**
     * List backup logs — paginated, filterable by tenant_id, status, tier, disk, date range.
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = Tenant::current();

        if (!$tenant) {
            return ApiResponse::customError('No active tenant found', 400);
        }

        $query = BackupLog::on('mysql')->forTenant($tenant->id)->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('tier')) {
            $query->byTier($request->tier);
        }

        if ($request->filled('disk')) {
            $query->where('disk', $request->disk);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $perPage = $request->get('per_page', 20);
        $logs    = $query->paginate($perPage);

        return ApiResponse::paginated('Backup logs retrieved successfully', $logs);
    }

    /**
     * Manually trigger a backup for the current tenant (runs synchronously).
     * Bypasses all schedule rules — always runs immediately.
     */
    public function trigger(BackupService $backupService, BackupStorageService $storageService): JsonResponse
    {
        $tenant = Tenant::current();

        if (!$tenant) {
            return ApiResponse::customError('No active tenant found', 400);
        }

        $log = $backupService->run($tenant, Auth::id());

        if ($log->status === BackupLog::STATUS_SUCCESS) {
            $storageService->pushToRemote($tenant, $log);
        }

        return ApiResponse::show('Backup completed', [
            'id'               => $log->id,
            'tenant_key'       => $log->tenant_key,
            'file_name'        => $log->file_name,
            'file_size'        => $log->file_size,
            'status'           => $log->status,
            'duration_seconds' => $log->duration_seconds,
            'error_message'    => $log->error_message,
        ]);
    }

    /**
     * Download a backup file by its log ID.
     */
    public function download(int $id): BinaryFileResponse|JsonResponse
    {
        $tenant = Tenant::current();
        $log    = BackupLog::on('mysql')->forTenant($tenant->id)->findOrFail($id);

        if (!Storage::disk('backup')->exists($log->file_path)) {
            return ApiResponse::notFound('Backup file not found on disk');
        }

        $fullPath = Storage::disk('backup')->path($log->file_path);

        return response()->download($fullPath, $log->file_name);
    }

    /**
     * Delete a backup log and its physical file.
     */
    public function destroy(int $id): JsonResponse
    {
        $tenant = Tenant::current();
        $log    = BackupLog::on('mysql')->forTenant($tenant->id)->findOrFail($id);

        if (Storage::disk('backup')->exists($log->file_path)) {
            Storage::disk('backup')->delete($log->file_path);
        }

        $log->delete();

        return ApiResponse::delete('Backup deleted successfully');
    }

    /**
     * Get all backup settings including schedule and retention config.
     * Credentials (keys, secrets, tokens) are not returned for security.
     */
    public function getSettings(): JsonResponse
    {
        $settings = [
            // Storage destinations
            'storage_drivers' => Setting::get('backup', 'storage_drivers', ['local'], false, Setting::TYPE_JSON),
            's3_bucket'       => Setting::get('backup', 's3_bucket'),
            's3_region'       => Setting::get('backup', 's3_region'),
            'ftp_host'        => Setting::get('backup', 'ftp_host'),
            'ftp_user'        => Setting::get('backup', 'ftp_user'),
            'ftp_port'        => Setting::get('backup', 'ftp_port', 21),
            'ftp_root'        => Setting::get('backup', 'ftp_root', '/'),
            // Schedule
            'frequency_hours'   => (int)  Setting::get('backup', 'frequency_hours',   24,   false, Setting::TYPE_NUMBER),
            'preferred_hour'    => (int)  Setting::get('backup', 'preferred_hour',    2,    false, Setting::TYPE_NUMBER),
            'skip_if_unchanged' => (bool) Setting::get('backup', 'skip_if_unchanged', true, false, Setting::TYPE_BOOLEAN),
            // Retention
            'retention_type'  => Setting::get('backup', 'retention_type',  'by_count', false, Setting::TYPE_STRING),
            'retention_value' => (int) Setting::get('backup', 'retention_value', 60,   false, Setting::TYPE_NUMBER),
        ];

        return ApiResponse::show('Backup settings retrieved successfully', $settings);
    }

    /**
     * Save backup settings (storage, schedule, and retention).
     * Local driver is always enforced — cannot be removed.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            // Storage
            'storage_drivers'   => 'sometimes|array',
            'storage_drivers.*' => 'string|in:local,s3,ftp,dropbox',
            's3_key'            => 'sometimes|string|max:255',
            's3_secret'         => 'sometimes|string|max:255',
            's3_bucket'         => 'sometimes|string|max:255',
            's3_region'         => 'sometimes|string|max:100',
            'ftp_host'          => 'sometimes|string|max:255',
            'ftp_user'          => 'sometimes|string|max:255',
            'ftp_password'      => 'sometimes|string|max:255',
            'ftp_port'          => 'sometimes|integer|min:1|max:65535',
            'ftp_root'          => 'sometimes|string|max:500',
            'dropbox_token'     => 'sometimes|string|max:500',
            // Schedule
            'frequency_hours'   => 'sometimes|integer|min:1|max:8760',
            'preferred_hour'    => 'sometimes|integer|min:0|max:23',
            'skip_if_unchanged' => 'sometimes|boolean',
            // Retention
            'retention_type'  => 'sometimes|string|in:by_count,by_days',
            'retention_value' => 'sometimes|integer|min:1|max:9999',
        ]);

        if ($request->has('storage_drivers')) {
            $drivers = array_unique(array_merge(['local'], $request->storage_drivers));
            Setting::set('backup', 'storage_drivers', json_encode(array_values($drivers)), Setting::TYPE_JSON, 'Backup storage destinations');
        }

        foreach (['s3_bucket', 's3_region', 'ftp_host', 'ftp_user', 'ftp_port', 'ftp_root'] as $key) {
            if ($request->has($key)) {
                Setting::set('backup', $key, $request->input($key));
            }
        }

        foreach (['s3_key', 's3_secret', 'ftp_password', 'dropbox_token'] as $key) {
            if ($request->has($key)) {
                $setting = Setting::firstOrNew(
                    ['group_name' => 'backup', 'key_name' => $key],
                    ['data_type' => Setting::TYPE_STRING, 'is_encrypted' => true]
                );
                $setting->is_encrypted = true;
                $setting->data_type    = Setting::TYPE_STRING;
                $setting->value        = $request->input($key);
                $setting->save();
            }
        }

        if ($request->has('frequency_hours')) {
            Setting::set('backup', 'frequency_hours', $request->frequency_hours, Setting::TYPE_NUMBER, 'Hours between scheduled backups');
        }

        if ($request->has('preferred_hour')) {
            Setting::set('backup', 'preferred_hour', $request->preferred_hour, Setting::TYPE_NUMBER, 'Hour of day (0-23) to run backups when frequency >= 24h');
        }

        if ($request->has('skip_if_unchanged')) {
            Setting::set('backup', 'skip_if_unchanged', $request->boolean('skip_if_unchanged') ? '1' : '0', Setting::TYPE_BOOLEAN, 'Skip backup if no data changed since last backup');
        }

        if ($request->has('retention_type')) {
            Setting::set('backup', 'retention_type', $request->retention_type, Setting::TYPE_STRING, 'Retention strategy: by_count or by_days');
        }

        if ($request->has('retention_value')) {
            Setting::set('backup', 'retention_value', $request->retention_value, Setting::TYPE_NUMBER, 'Number of backups to keep (by_count) or days to retain (by_days)');
        }

        return ApiResponse::update('Backup settings updated successfully', [
            'storage_drivers'   => Setting::get('backup', 'storage_drivers',   ['local'], false, Setting::TYPE_JSON),
            'frequency_hours'   => (int)  Setting::get('backup', 'frequency_hours',   24,   false, Setting::TYPE_NUMBER),
            'preferred_hour'    => (int)  Setting::get('backup', 'preferred_hour',    2,    false, Setting::TYPE_NUMBER),
            'skip_if_unchanged' => (bool) Setting::get('backup', 'skip_if_unchanged', true, false, Setting::TYPE_BOOLEAN),
            'retention_type'    => Setting::get('backup', 'retention_type',  'by_count', false, Setting::TYPE_STRING),
            'retention_value'   => (int)  Setting::get('backup', 'retention_value', 60,   false, Setting::TYPE_NUMBER),
        ]);
    }

    /**
     * Show what the scheduler would do right now for this tenant.
     * Use this to verify that your schedule and retention settings are correct
     * without actually running a backup.
     */
    public function scheduleStatus(BackupService $backupService): JsonResponse
    {
        $tenant = Tenant::current();

        if (!$tenant) {
            return ApiResponse::customError('No active tenant found', 400);
        }

        $frequencyHours  = (int)  Setting::get('backup', 'frequency_hours',   24,   false, Setting::TYPE_NUMBER);
        $preferredHour   = (int)  Setting::get('backup', 'preferred_hour',    2,    false, Setting::TYPE_NUMBER);
        $skipIfUnchanged = (bool) Setting::get('backup', 'skip_if_unchanged', true, false, Setting::TYPE_BOOLEAN);
        $retentionType   = Setting::get('backup', 'retention_type',  'by_count', false, Setting::TYPE_STRING);
        $retentionValue  = (int)  Setting::get('backup', 'retention_value', 60,   false, Setting::TYPE_NUMBER);

        $lastBackup = BackupLog::on('mysql')
            ->forTenant($tenant->id)
            ->successful()
            ->latest()
            ->first();

        $wouldRun    = true;
        $skipReasons = [];

        if ($frequencyHours >= 24 && now()->hour !== $preferredHour) {
            $wouldRun      = false;
            $skipReasons[] = 'Not preferred hour: current is ' . now()->hour . ':00, preferred is ' . $preferredHour . ':00';
        }

        if ($lastBackup) {
            $elapsed = (int) $lastBackup->created_at->diffInHours(now());
            if ($elapsed < $frequencyHours) {
                $wouldRun      = false;
                $skipReasons[] = "Frequency not reached: {$elapsed}h elapsed, need {$frequencyHours}h";
            }
        }

        $dataChanged = null;
        if ($skipIfUnchanged && $lastBackup) {
            $dataChanged = $backupService->hasDataChangedSince($tenant, $lastBackup->created_at);
            if (!$dataChanged) {
                $wouldRun      = false;
                $skipReasons[] = 'No data changes since last backup';
            }
        }

        // Next expected run: last backup time + frequency, snapped to preferred hour when >= 24h
        $nextExpectedAt = null;
        if ($lastBackup) {
            $next = $lastBackup->created_at->copy()->addHours($frequencyHours);
            if ($frequencyHours >= 24) {
                $next->setHour($preferredHour)->setMinute(0)->setSecond(0);
                if ($next->isPast()) {
                    $next->addDay();
                }
            }
            $nextExpectedAt = $next->toDateTimeString();
        }

        return ApiResponse::show('Backup schedule status', [
            'would_run_now'                => $wouldRun,
            'skip_reasons'                 => $skipReasons,
            'data_changed_since_last_backup' => $dataChanged,
            'last_backup_at'               => $lastBackup?->created_at?->toDateTimeString(),
            'last_backup_file'             => $lastBackup?->file_name,
            'next_expected_at'             => $nextExpectedAt,
            'current_server_hour'          => now()->hour,
            'settings' => [
                'frequency_hours'   => $frequencyHours,
                'preferred_hour'    => $preferredHour,
                'skip_if_unchanged' => $skipIfUnchanged,
                'retention_type'    => $retentionType,
                'retention_value'   => $retentionValue,
            ],
        ]);
    }

    // TODO: Implement testConnection(Request $request): JsonResponse
    // Test if a remote storage driver (ftp, s3, dropbox) is reachable and writable.
    // Steps:
    //   1. Accept a driver name (ftp, s3, dropbox) in the request
    //   2. Build the disk using BackupStorageService::buildDisk()
    //   3. Write a small temp file (e.g. ".connection-test"), then delete it
    //   4. Return success/failure with a descriptive message
    // Route: POST /backups/settings/test-connection
}
