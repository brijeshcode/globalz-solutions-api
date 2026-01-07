<?php

namespace App\Http\Controllers\Api\Items;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Items\ItemAdjustsStoreRequest;
use App\Http\Requests\Api\Items\ItemAdjustsUpdateRequest;
use App\Http\Resources\Api\Items\ItemAdjustResource;
use App\Models\Items\ItemAdjust;
use App\Services\Items\ItemAdjustService;
use App\Traits\HasPagination;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemAdjustsController extends Controller
{
    use HasPagination;

    protected $itemAdjustService;

    public function __construct(ItemAdjustService $itemAdjustService)
    {
        if (!RoleHelper::canWarehouseManager()) {
            abort(403, 'Unauthorized. warehouse access required.');
        }

        $this->itemAdjustService = $itemAdjustService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->itemAdjustQuery($request);

        $itemAdjusts = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Item adjustments retrieved successfully',
            $itemAdjusts,
            ItemAdjustResource::class
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ItemAdjustsStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $items = $data['items'] ?? [];
        unset($data['items']); // Remove items from item adjust data

        // Create item adjust with items using service
        $itemAdjust = $this->itemAdjustService->createItemAdjustWithItems($data, $items);

        $itemAdjust->load([
            'createdBy:id,name',
            'updatedBy:id,name',
            'warehouse:id,name',
            'itemAdjustItems.item:id,code,short_name',
        ]);

        return ApiResponse::store(
            'Item adjustment created successfully',
            new ItemAdjustResource($itemAdjust)
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(ItemAdjust $itemAdjust): JsonResponse
    {
        $itemAdjust->load([
            'createdBy:id,name',
            'updatedBy:id,name',
            'warehouse:id,name',
            'itemAdjustItems.item:id,code,short_name,description',
        ]);

        return ApiResponse::show(
            'Item adjustment retrieved successfully',
            new ItemAdjustResource($itemAdjust)
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ItemAdjustsUpdateRequest $request, ItemAdjust $itemAdjust): JsonResponse
    {
        $data = $request->validated();
        $items = $data['items'] ?? [];
        unset($data['items']); // Remove items from item adjust data
        unset($data['code']); // Remove code from data if present (code is system generated only, not updatable)

        // Update item adjust with items using service
        $itemAdjust = $this->itemAdjustService->updateItemAdjustWithItems($itemAdjust, $data, $items);

        $itemAdjust->load([
            'createdBy:id,name',
            'updatedBy:id,name',
            'warehouse:id,name',
            'itemAdjustItems.item:id,code,short_name',
        ]);

        return ApiResponse::update(
            'Item adjustment updated successfully',
            new ItemAdjustResource($itemAdjust)
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ItemAdjust $itemAdjust): JsonResponse
    {
        if (!RoleHelper::canAdmin()) {
            abort(403, 'Unauthorized. Admin access required.');
        }
        $this->itemAdjustService->deleteItemAdjust($itemAdjust);

        return ApiResponse::delete('Item adjustment deleted successfully');
    }

    /**
     * Display a listing of trashed resources.
     */
    public function trashed(Request $request): JsonResponse
    {
        if (!RoleHelper::canAdmin()) {
            abort(403, 'Unauthorized. Admin access required.');
        }

        $query = ItemAdjust::onlyTrashed()
            ->with([
                'createdBy:id,name',
                'updatedBy:id,name',
                'warehouse:id,name',
            ])
            ->searchable($request)
            ->sortable($request);

        $itemAdjusts = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed item adjustments retrieved successfully',
            $itemAdjusts,
            ItemAdjustResource::class
        );
    }

    /**
     * Restore the specified resource from trash.
     */
    public function restore(int $id): JsonResponse
    {
        if (!RoleHelper::canSuperAdmin()) {
            abort(403, 'Unauthorized. Admin access required.');
        }

        $itemAdjust = ItemAdjust::onlyTrashed()->findOrFail($id);
        $this->itemAdjustService->restoreItemAdjust($itemAdjust);

        return ApiResponse::update('Item adjustment restored successfully');
    }

    /**
     * Permanently delete the specified resource.
     */
    public function forceDelete(int $id): JsonResponse
    {
        if (!RoleHelper::canSuperAdmin()) {
            abort(403, 'Unauthorized. Admin access required.');
        }

        $itemAdjust = ItemAdjust::onlyTrashed()->findOrFail($id);
        $itemAdjust->forceDelete();

        return ApiResponse::delete('Item adjustment permanently deleted successfully');
    }

    /**
     * Get statistics for item adjusts
     */
    public function stats(Request $request): JsonResponse
    {
        $query = $this->itemAdjustQuery($request);

        $stats = [
            'total_adjustments' => (clone $query)->count(),
            'total_add_adjustments' => (clone $query)->where('type', 'Add')->count(),
            'total_subtract_adjustments' => (clone $query)->where('type', 'Subtract')->count(),
            'total_items_adjusted' => (clone $query)
                ->join('item_adjust_items', 'item_adjusts.id', '=', 'item_adjust_items.item_adjust_id')
                ->sum('item_adjust_items.quantity'),
        ];

        return ApiResponse::show('Item adjustment statistics retrieved successfully', $stats);
    }

    /**
     * Build the item adjust query with filters
     */
    private function itemAdjustQuery(Request $request)
    {
        $query = ItemAdjust::query()
            ->with([
                'createdBy:id,name',
                'updatedBy:id,name',
                'warehouse:id,name',
            ])
            ->searchable($request)
            ->sortable($request);

        if (RoleHelper::isWarehouseManager()) {
            $employee = RoleHelper::getWarehouseEmployee();
            if (! $employee) {
                return $query->whereRaw('1 = 0');
            }
            $warehouseIds = $employee->warehouses()->pluck('warehouses.id');
            if ($warehouseIds->isEmpty()) {
                return $query->whereRaw('1 = 0');
            }

            if ($request->has('warehouse_id')) {
                // Only allow filtering by warehouse_id if it's in their assigned warehouses
                if ($warehouseIds->contains($request->warehouse_id)) {
                    $query->byWarehouse($request->warehouse_id);
                } else {
                    $query->whereIn('warehouse_id', $warehouseIds);
                }
            } else {
                $query->whereIn('warehouse_id', $warehouseIds);
            }
        }elseif ($request->has('warehouse_id')) {
            $query->byWarehouse($request->warehouse_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('code')) {
            $query->byCode($request->input('code'));
        }

        if ($request->has('from_date')) {
            $query->where('date', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('date', '<=', $request->to_date);
        }

        return $query;
    }
}
