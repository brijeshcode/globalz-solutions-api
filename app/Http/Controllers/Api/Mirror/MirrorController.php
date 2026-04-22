<?php

namespace App\Http\Controllers\Api\Mirror;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\MirrorLog;
use App\Models\Setting;
use App\Jobs\MirrorTenantJob;
use App\Models\Tenant;
use App\Services\Mirror\DatabaseMirrorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MirrorController extends Controller
{
    public function __construct()
    {
        if (!RoleHelper::canSuperAdmin()) {
            abort(403, 'Unauthorized');
        }
    }

    /**
     * Get current mirror settings (password is never returned).
     */
    public function getSettings(): JsonResponse
    {
        return ApiResponse::show('Mirror settings retrieved successfully', [
            'enabled'       => Setting::get('mirror', 'enabled', false, false, Setting::TYPE_BOOLEAN),
            'db_type'       => Setting::get('mirror', 'db_type', 'mysql'),
            'host'          => Setting::get('mirror', 'host', ''),
            'port'          => (int) Setting::get('mirror', 'port', 3306, false, Setting::TYPE_NUMBER),
            'database'      => Setting::get('mirror', 'database', ''),
            'username'      => Setting::get('mirror', 'username', ''),
            'store_limit'   => (int) Setting::get('mirror', 'store_limit', 1000, false, Setting::TYPE_NUMBER),
            'display_limit' => (int) Setting::get('mirror', 'display_limit', 25, false, Setting::TYPE_NUMBER),
        ]);
    }

    /**
     * Save mirror credentials and settings.
     * Password only updated when explicitly provided.
     */
    public function updateSettings(Request $request, DatabaseMirrorService $mirrorService): JsonResponse
    {
        $request->validate([
            'enabled'       => 'sometimes|boolean',
            'db_type'       => 'sometimes|string|in:mysql',
            'host'          => 'sometimes|string|max:255',
            'port'          => 'sometimes|integer|min:1|max:65535',
            'database'      => 'sometimes|string|max:255',
            'username'      => 'sometimes|string|max:255',
            'password'      => 'sometimes|string|max:255',
            'store_limit'   => 'sometimes|integer|min:1|max:10000',
            'display_limit' => 'sometimes|integer|min:1|max:1000',
        ]);

        if ($request->filled('host')) {
            try {
                $mirrorService->validateHost($request->host);
            } catch (\InvalidArgumentException $e) {
                return ApiResponse::failValidation(['host' => [$e->getMessage()]]);
            }
        }

        foreach (['db_type', 'host', 'database', 'username'] as $key) {
            if ($request->has($key)) {
                Setting::set('mirror', $key, $request->input($key), Setting::TYPE_STRING);
            }
        }

        if ($request->has('enabled')) {
            Setting::set('mirror', 'enabled', $request->boolean('enabled') ? '1' : '0', Setting::TYPE_BOOLEAN);
        }

        foreach (['port', 'store_limit', 'display_limit'] as $key) {
            if ($request->has($key)) {
                Setting::set('mirror', $key, $request->input($key), Setting::TYPE_NUMBER);
            }
        }

        if ($request->filled('password')) {
            $setting = Setting::firstOrNew(
                ['group_name' => 'mirror', 'key_name' => 'password'],
                ['data_type' => Setting::TYPE_STRING, 'is_encrypted' => true]
            );
            $setting->is_encrypted = true;
            $setting->data_type    = Setting::TYPE_STRING;
            $setting->value        = $request->password;
            $setting->save();
        }

        return ApiResponse::update('Mirror settings updated successfully', [
            'enabled' => Setting::get('mirror', 'enabled', false, false, Setting::TYPE_BOOLEAN),
        ]);
    }

    /**
     * Manually trigger a mirror for the current tenant.
     * Dispatches a background job — returns immediately with the job queued status.
     * Frontend should poll GET /mirrors/logs to track progress.
     */
    public function trigger(): JsonResponse
    {
        $tenant = Tenant::current();

        if (!$tenant) {
            return ApiResponse::customError('No active tenant found', 400);
        }
        
        MirrorTenantJob::dispatch($tenant->id, Auth::id());

        return ApiResponse::show('Mirror job queued — check logs for progress.');
    }

    /**
     * Return last N mirror logs (N = display_limit setting, default 25).
     */
    public function logs(): JsonResponse
    {
        $limit = (int) Setting::get('mirror', 'display_limit', 25, false, Setting::TYPE_NUMBER);
        $logs  = MirrorLog::latestFirst()->limit($limit)->get();

        return ApiResponse::show('Mirror logs retrieved successfully', $logs);
    }
}
