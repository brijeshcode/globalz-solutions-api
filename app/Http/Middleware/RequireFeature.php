<?php

namespace App\Http\Middleware;

use App\Helpers\FeatureHelper;
use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (!FeatureHelper::isEnabled($feature)) {
            return ApiResponse::forbidden("The '{$feature}' feature is not enabled for this tenant.");
        }

        return $next($request);
    }
}
