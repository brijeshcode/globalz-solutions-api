<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Landlord\TenantFeature;
use Illuminate\Http\JsonResponse;

class FeatureFlagsController extends Controller
{
    /**
     * Return all feature flags for the current tenant.
     * Frontend should cache this response for up to 7 days and
     * refresh only when the X-Cache-Versions 'features' key changes.
     */
    public function index(): JsonResponse
    {
        return ApiResponse::show('Features retrieved1 successfully.', TenantFeature::getForCurrentTenant());
    }
}
