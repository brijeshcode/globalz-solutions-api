<?php

namespace App\Http\Controllers\Api;

use App\Helpers\RoleHelper;
use App\Helpers\SettingsHelper;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AttachCacheVersion;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class BugLockController extends Controller
{
    // Error code sent to clients when bug lock is active
    const ERROR_CODE = 'BUG_SERVICE_NOT_AVAILABLE';

    // Default message shown when bug lock is enabled without a custom message
    const DEFAULT_MESSAGE = 'EXCEPTION_UNHANDLED_STACK [0x00000005]: kernel32.dll assertion failed at 0x7FFE0300 — heap corruption detected in worker thread pool. ERR_PIPE_BROKEN :: upstream socket closed unexpectedly (errno 104). Retrying... [attempt 3/3 failed]. Core dumped.';

    public function enable(): JsonResponse
    {
        if (!RoleHelper::canAdmin()) {
            return ApiResponse::forbidden('You do not have permission to enable bug lock.');
        }

        SettingsHelper::enableBugLock(self::DEFAULT_MESSAGE);
        AttachCacheVersion::invalidate('global');

        return ApiResponse::send('Bug lock enabled. All non-login requests are now blocked.', 200, [
            'bug_lock' => true,
            'error_code' => self::ERROR_CODE,
            'message' => self::DEFAULT_MESSAGE,
        ]);
    }

    public function disable(): JsonResponse
    {
        if (!RoleHelper::canSuperAdmin()) {
            return ApiResponse::forbidden('Only super admins can disable bug lock.');
        }

        SettingsHelper::disableBugLock();

        return ApiResponse::send('Bug lock disabled. System is now accessible.', 200, [
            'bug_lock' => false,
        ]);
    }

    public function status(): JsonResponse
    {
        // if (!RoleHelper::canAdmin()) {
        //     return ApiResponse::forbidden('You do not have permission to view bug lock status.');
        // }

        $enabled = SettingsHelper::isBugLockEnabled();

        return ApiResponse::send('Bug lock status retrieved.', 200, [
            'bug_lock' => $enabled,
            'error_code' => self::ERROR_CODE,
            'message' => $enabled ? SettingsHelper::getBugLockMessage() : null,
        ]);
    }
}
