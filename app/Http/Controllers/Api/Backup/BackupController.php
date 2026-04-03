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
     * Returns the result immediately — no queue worker needed.
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
     * Get the current tenant's backup storage settings.
     * Credentials (keys, secrets, tokens) are not returned for security.
     */
    public function getSettings(): JsonResponse
    {
        $settings = [
            'storage_drivers' => Setting::get('backup', 'storage_drivers', ['local'], false, Setting::TYPE_JSON),
            's3_bucket'       => Setting::get('backup', 's3_bucket'),
            's3_region'       => Setting::get('backup', 's3_region'),
            'ftp_host'        => Setting::get('backup', 'ftp_host'),
            'ftp_user'        => Setting::get('backup', 'ftp_user'),
            'ftp_port'        => Setting::get('backup', 'ftp_port', 21),
        ];

        return ApiResponse::show('Backup settings retrieved successfully', $settings);
    }

    /**
     * Save the current tenant's backup storage settings.
     * Local driver is always enforced — cannot be removed.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
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
            'dropbox_token'     => 'sometimes|string|max:500',
        ]);

        if ($request->has('storage_drivers')) {
            // Enforce local is always present
            $drivers = array_unique(array_merge(['local'], $request->storage_drivers));
            Setting::set('backup', 'storage_drivers', json_encode(array_values($drivers)), Setting::TYPE_JSON, 'Backup storage destinations');
        }

        // Plain (non-sensitive) settings
        foreach (['s3_bucket', 's3_region', 'ftp_host', 'ftp_user', 'ftp_port'] as $key) {
            if ($request->has($key)) {
                Setting::set('backup', $key, $request->input($key));
            }
        }

        // Encrypted settings — store with is_encrypted = true
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

        return ApiResponse::update('Backup settings updated successfully', [
            'storage_drivers' => Setting::get('backup', 'storage_drivers', ['local'], false, Setting::TYPE_JSON),
        ]);
    }
}
