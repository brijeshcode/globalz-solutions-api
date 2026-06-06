<?php

namespace App\Http\Controllers\Api;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AttachCacheVersion;
use App\Http\Responses\ApiResponse;
use App\Services\Currency\CurrencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CacheVersionController extends Controller
{
    /**
     * Get current cache versions (called on app startup)
     */
    public function index(): JsonResponse
    {
        return ApiResponse::show(
            'Cache versions retrieved successfully',
            AttachCacheVersion::getVersions()
        );
    }

    /**
     * Invalidate a specific cache key or all (global)
     */
    public function invalidate(Request $request): JsonResponse
    {
        if (!RoleHelper::canAdmin()) {
            return ApiResponse::customError('Only admin users can invalidate cache', 403);
        }

        $validated = $request->validate([
            'key' => 'required|string',
        ]);

        if ($validated['key'] === 'local_currency') {
            Cache::forget(CurrencyService::LOCAL_CURRENCY_CACHE_KEY);
            return ApiResponse::update("Cache 'local_currency' invalidated successfully", []);
        }

        $versions = AttachCacheVersion::invalidate($validated['key']);

        return ApiResponse::update(
            "Cache '{$validated['key']}' invalidated successfully",
            $versions
        );
    }
}
