<?php

namespace App\Http\Controllers\Api\Items;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Inventory\PriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemPriceAuditController extends Controller
{
    public function audit(Request $request): JsonResponse
    {
        $tolerance = (float) $request->query('tolerance', 2.0);
        return ApiResponse::show('Item price scan complete', PriceService::auditItemPrices($tolerance));
    }

    public function auditItem(Request $request, int $itemId): JsonResponse
    {
        $tolerance = (float) $request->query('tolerance', 2.0);
        return ApiResponse::show('Item price scan complete', PriceService::auditSingleItemPrice($itemId, $tolerance));
    }

    public function fix(Request $request): JsonResponse
    {
        $tolerance = (float) $request->query('tolerance', 2.0);
        return ApiResponse::show('Item price fix complete', PriceService::auditAndFixItemPrices($tolerance));
    }

    public function fixItem(Request $request, int $itemId): JsonResponse
    {
        $tolerance = (float) $request->query('tolerance', 2.0);
        return ApiResponse::show('Item price fix complete', PriceService::auditAndFixSingleItemPrice($itemId, $tolerance));
    }
}
