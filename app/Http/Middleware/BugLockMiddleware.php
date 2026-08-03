<?php

namespace App\Http\Middleware;

use App\Helpers\RoleHelper;
use App\Helpers\SettingsHelper;
use App\Http\Controllers\Api\BugLockController;
use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BugLockMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!SettingsHelper::isBugLockEnabled()) {
            return $next($request);
        }

        // Allow login route through
        if ($request->routeIs('login')) {
            return $next($request);
        }

        // Super admins and developers are never locked out
        if (RoleHelper::canSuperAdmin()) {
            return $next($request);
        }

        $message = 'Service not avaiable';

        return ApiResponse::custom($message, 503, [
            'error_code' => BugLockController::ERROR_CODE,
            'message' => BugLockController::DEFAULT_MESSAGE,
        ]);
    }
}
