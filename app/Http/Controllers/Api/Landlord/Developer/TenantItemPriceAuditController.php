<?php

namespace App\Http\Controllers\Api\Landlord\Developer;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant;
use App\Services\Inventory\PriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TenantItemPriceAuditController extends Controller
{
    public function __construct()
    {
        if (!RoleHelper::canDeveloper()) {
            abort(403, 'Only developers can access this.');
        }
    }

    public function audit(Tenant $tenant): JsonResponse
    {
        $result = $tenant->execute(fn () => PriceService::auditItemPrices());

        Log::info('[Developer] Item price scan', [
            'tenant_id'          => $tenant->id,
            'tenant_name'        => $tenant->name,
            'total_checked'      => $result['total_items_checked'] ?? 0,
            'items_to_fix'       => $result['items_to_fix'] ?? 0,
            'items_missing_price'=> $result['items_missing_price'] ?? 0,
            'executed_by'        => Auth::user()?->email ?? 'System',
        ]);

        return ApiResponse::show('Item price scan complete — no changes made', $result);
    }

    public function auditItem(Tenant $tenant, int $itemId): JsonResponse
    {
        $result = $tenant->execute(fn () => PriceService::auditSingleItemPrice($itemId));

        return ApiResponse::show('Item price scan complete (no changes made)', $result);
    }

    public function fixItem(Tenant $tenant, int $itemId): JsonResponse
    {
        $result = $tenant->execute(fn () => PriceService::auditAndFixSingleItemPrice($itemId));

        Log::info('[Developer] Item price fix for single item', [
            'tenant_id'   => $tenant->id,
            'tenant_name' => $tenant->name,
            'item_id'     => $itemId,
            'executed_by' => Auth::user()?->email ?? 'System',
        ]);

        return ApiResponse::show('Item price fix complete', $result);
    }

    public function fix(Tenant $tenant): JsonResponse
    {
        $result = $tenant->execute(fn () => PriceService::auditAndFixItemPrices());

        Log::info('[Developer] Item price scan and fix executed', [
            'tenant_id'      => $tenant->id,
            'tenant_name'    => $tenant->name,
            'items_fixed'    => count($result['fixed'] ?? []),
            'items_created'  => count($result['created'] ?? []),
            'items_skipped'  => count($result['skipped'] ?? []),
            'executed_by'    => Auth::user()?->email ?? 'System',
        ]);

        return ApiResponse::show('Item price scan and fix complete', $result);
    }
}
