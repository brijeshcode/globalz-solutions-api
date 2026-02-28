<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AttachCacheVersion;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantCacheController extends Controller
{
    /**
     * Invalidate a specific cache version key for a tenant.
     *
     * POST /tenants/{tenant}/cache/invalidate
     *
     * Body:
     *   key (string, required) — the cache version key to bump.
     *                            Use 'global' to bump all keys at once.
     *
     * Examples: 'tenant_details', 'currencies', 'global'
     */
    public function invalidate(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'key' => 'required|string|max:100',
        ]);

        $versions = $tenant->execute(
            fn () => AttachCacheVersion::invalidate($validated['key'])
        );

        return ApiResponse::send('Cache invalidated successfully', 200, [
            'tenant_id'   => $tenant->id,
            'tenant_name' => $tenant->name,
            'key'         => $validated['key'],
            'versions'    => $versions,
        ]);
    }
}
