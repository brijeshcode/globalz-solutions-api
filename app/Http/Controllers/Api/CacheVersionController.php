<?php

namespace App\Http\Controllers\Api;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AttachCacheVersion;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $versions = AttachCacheVersion::invalidate($validated['key']);

        return ApiResponse::update(
            "Cache '{$validated['key']}' invalidated successfully",
            $versions
        );
    }
}
