<?php

namespace App\Http\Controllers\Api\Items;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Items\ItemTransfersStoreRequest;
use App\Http\Requests\Api\Items\ItemTransfersUpdateRequest;
use App\Http\Resources\Api\Items\ItemTransferResource;
use App\Models\Items\ItemTransfer;
use App\Services\Items\ItemTransferService;
use App\Traits\HasPagination;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemTransfersController extends Controller
{
    use HasPagination;

    protected $itemTransferService;

    public function __construct(ItemTransferService $itemTransferService)
    {
        $this->itemTransferService = $itemTransferService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->itemTransferQuery($request);

        $itemTransfers = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Item transfers retrieved successfully',
            $itemTransfers,
            ItemTransferResource::class
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ItemTransfersStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $items = $data['items'] ?? [];
        unset($data['items']); // Remove items from item transfer data

        // Create item transfer with items using service
        $itemTransfer = $this->itemTransferService->createItemTransferWithItems($data, $items);

        $itemTransfer->load([
            'createdBy:id,name',
            'updatedBy:id,name',
            'fromWarehouse:id,name',
            'toWarehouse:id,name',
            'itemTransferItems.item:id,code,short_name',
        ]);

        return ApiResponse::store(
            'Item transfer created successfully',
            new ItemTransferResource($itemTransfer)
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(ItemTransfer $itemTransfer): JsonResponse
    {
        $itemTransfer->load([
            'createdBy:id,name',
            'updatedBy:id,name',
            'fromWarehouse:id,name',
            'toWarehouse:id,name',
            'itemTransferItems.item:id,code,short_name,description',
        ]);

        return ApiResponse::show(
            'Item transfer retrieved successfully',
            new ItemTransferResource($itemTransfer)
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ItemTransfersUpdateRequest $request, ItemTransfer $itemTransfer): JsonResponse
    {
        $data = $request->validated();
        $items = $data['items'] ?? [];
        unset($data['items']); // Remove items from item transfer data
        unset($data['code']); // Remove code from data if present (code is system generated only, not updatable)

        // Update item transfer with items using service
        $itemTransfer = $this->itemTransferService->updateItemTransferWithItems($itemTransfer, $data, $items);

        $itemTransfer->load([
            'createdBy:id,name',
            'updatedBy:id,name',
            'fromWarehouse:id,name',
            'toWarehouse:id,name',
            'itemTransferItems.item:id,code,short_name',
        ]);

        return ApiResponse::update(
            'Item transfer updated successfully',
            new ItemTransferResource($itemTransfer)
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ItemTransfer $itemTransfer): JsonResponse
    {
        if(! RoleHelper::canAdmin()){
            return ApiResponse::forbidden('you are not authorize');
        }
        
        $itemTransfer->delete();

        return ApiResponse::delete('Item transfer deleted successfully');
    }

    /**
     * Display a listing of trashed resources.
     */
    public function trashed(Request $request): JsonResponse
    {
        $query = ItemTransfer::onlyTrashed()
            ->with([
                'createdBy:id,name',
                'updatedBy:id,name',
                'fromWarehouse:id,name',
                'toWarehouse:id,name',
            ])
            ->searchable($request)
            ->sortable($request);

        $itemTransfers = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed item transfers retrieved successfully',
            $itemTransfers,
            ItemTransferResource::class
        );
    }

    /**
     * Restore the specified resource from trash.
     */
    public function restore(int $id): JsonResponse
    {
        $itemTransfer = ItemTransfer::onlyTrashed()->findOrFail($id);
        $itemTransfer->restore();

        return ApiResponse::update('Item transfer restored successfully');
    }

    /**
     * Permanently delete the specified resource.
     */
    public function forceDelete(int $id): JsonResponse
    {
        $itemTransfer = ItemTransfer::onlyTrashed()->findOrFail($id);
        $itemTransfer->forceDelete();

        return ApiResponse::delete('Item transfer permanently deleted successfully');
    }

    /**
     * Get statistics for item transfers
     */
    public function stats(Request $request): JsonResponse
    {
        $query = $this->itemTransferQuery($request);

        $stats = [
             
            'total_transfers' => (clone $query)->count(),
            'total_items_transferred' => (clone $query)
                ->join('item_transfer_items', 'item_transfers.id', '=', 'item_transfer_items.item_transfer_id')
                ->sum('item_transfer_items.quantity'),
        ];

        return ApiResponse::show('Item transfer statistics retrieved successfully', $stats);
    }

    /**
     * Build the item transfer query with filters
     */
    private function itemTransferQuery(Request $request)
    {
        $query = ItemTransfer::query()
            ->with([
                'createdBy:id,name',
                'updatedBy:id,name',
                'fromWarehouse:id,name',
                'toWarehouse:id,name',
            ])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('from_warehouse_id')) {
            $query->where('from_warehouse_id', $request->input('from_warehouse_id'));
        }

        if ($request->has('to_warehouse_id')) {
            $query->where('to_warehouse_id', $request->input('to_warehouse_id'));
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
