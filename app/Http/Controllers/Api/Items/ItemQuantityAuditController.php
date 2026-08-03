<?php

namespace App\Http\Controllers\Api\Items;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Items\Item;
use App\Services\Inventory\QuantityAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemQuantityAuditController extends Controller
{
    public function audit(Request $request): JsonResponse
    {
        $warehouseId = $request->filled('warehouse_id') ? (int) $request->query('warehouse_id') : null;
        return ApiResponse::show('Item quantity scan complete', QuantityAuditService::auditQuantities($warehouseId));
    }

    public function auditItem(Request $request, Item $item): JsonResponse
    {
        $warehouseId = $request->filled('warehouse_id') ? (int) $request->query('warehouse_id') : null;
        return ApiResponse::show('Item quantity scan complete', QuantityAuditService::auditSingleItemQuantity($item->id, $warehouseId));
    }

    public function fix(Request $request): JsonResponse
    {
        $warehouseId = $request->filled('warehouse_id') ? (int) $request->query('warehouse_id') : null;
        $dryRun      = $request->boolean('dry_run', false);
        return ApiResponse::show('Item quantity fix complete', QuantityAuditService::auditAndFixQuantities($warehouseId, $dryRun));
    }

    public function fixItem(Request $request, Item $item): JsonResponse
    {
        $warehouseId = $request->filled('warehouse_id') ? (int) $request->query('warehouse_id') : null;
        $dryRun      = $request->boolean('dry_run', false);
        return ApiResponse::show('Item quantity fix complete', QuantityAuditService::auditAndFixSingleItemQuantity($item->id, $warehouseId, $dryRun));
    }
}
