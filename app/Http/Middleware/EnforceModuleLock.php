<?php

namespace App\Http\Middleware;

use App\Contracts\ModuleLockable;
use App\Helpers\SettingsHelper;
use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceModuleLock
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach ($request->route()->parameters() as $parameter) {
            if ($parameter instanceof ModuleLockable && SettingsHelper::isRecordLocked($parameter)) {
                $days = SettingsHelper::moduleLockDays($parameter->moduleLockKey());

                return ApiResponse::forbidden(
                    "This record is locked because it is older than {$days} days. Only a super admin can modify or delete it."
                );
            }
        }

        return $next($request);
    }
}
