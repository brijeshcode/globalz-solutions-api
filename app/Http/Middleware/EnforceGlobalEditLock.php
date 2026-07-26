<?php

namespace App\Http\Middleware;

use App\Helpers\RoleHelper;
use App\Helpers\SettingsHelper;
use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceGlobalEditLock
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only edit/delete requests are gated; creates (POST) and reads (GET) pass through.
        if (! in_array($request->method(), ['PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        if (! SettingsHelper::isGlobalEditBlocked()) {
            return $next($request);
        }

        // Super admins and developers are never locked out.
        if (RoleHelper::canSuperAdmin()) {
            return $next($request);
        }

        return ApiResponse::forbidden(
            'Editing and deleting are currently disabled. Only a super admin can modify or delete records.'
        );
    }
}
