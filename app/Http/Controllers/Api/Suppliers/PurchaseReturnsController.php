<?php

namespace App\Http\Controllers\Api\Suppliers;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Suppliers\PurchaseReturnsStoreRequest;
use App\Http\Requests\Api\Suppliers\PurchaseReturnsUpdateRequest;
use App\Http\Resources\Api\Suppliers\PurchaseReturnResource;
use App\Models\Suppliers\PurchaseReturn;
use App\Services\Suppliers\PurchaseReturnService;
use App\Traits\HasPagination;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseReturnsController extends Controller
{
    use HasPagination;

    protected $purchaseReturnService;

    public function __construct(PurchaseReturnService $purchaseReturnService)
    {
        $this->purchaseReturnService = $purchaseReturnService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->purchaseReturnQuery($request);

        $purchaseReturns = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Purchase returns retrieved successfully',
            $purchaseReturns,
            PurchaseReturnResource::class
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PurchaseReturnsStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $items = $data['items'] ?? [];
        unset($data['items']); // Remove items from purchase return data

        // Create purchase return with items using service
        $purchaseReturn = $this->purchaseReturnService->createPurchaseReturnWithItems($data, $items);

        // Handle document uploads
        if ($request->hasFile('documents')) {
            $files = $request->file('documents');
            if (!is_array($files)) {
                $files = [$files];
            }

            // Validate each document file
            foreach ($files as $file) {
                $validationErrors = $purchaseReturn->validateDocumentFile($file);
                if (!empty($validationErrors)) {
                    return ApiResponse::customError('Document validation failed: ' . implode(', ', $validationErrors), 422);
                }
            }

            // Upload documents
            $purchaseReturn->createDocuments($files, [
                'type' => 'purchase_return_document'
            ]);
        }

        $purchaseReturn->load([
            'createdBy:id,name',
            'updatedBy:id,name',
            'supplier:id,code,name',
            'warehouse:id,name',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'purchaseReturnItems.item:id,code,short_name',
            'documents'
        ]);

        return ApiResponse::store(
            'Purchase return created successfully',
            new PurchaseReturnResource($purchaseReturn)
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(PurchaseReturn $purchaseReturn): JsonResponse
    {
        $purchaseReturn->load([
            'createdBy:id,name',
            'updatedBy:id,name',
            'supplier:id,code,name',
            'warehouse:id,name',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'purchaseReturnItems.item:id,code,short_name',
            'documents'
        ]);

        return ApiResponse::show(
            'Purchase return retrieved successfully',
            new PurchaseReturnResource($purchaseReturn)
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PurchaseReturnsUpdateRequest $request, PurchaseReturn $purchaseReturn): JsonResponse
    {
        $data = $request->validated();
        $items = $data['items'] ?? [];
        unset($data['items']); // Remove items from purchase return data
        unset($data['code']); // Remove code from data if present (code is system generated only, not updatable)

        // Update purchase return with items using service
        $purchaseReturn = $this->purchaseReturnService->updatePurchaseReturnWithItems($purchaseReturn, $data, $items);

        // Handle document uploads
        if ($request->hasFile('documents')) {
            $files = $request->file('documents');
            if (!is_array($files)) {
                $files = [$files];
            }

            // Validate each document file
            foreach ($files as $file) {
                $validationErrors = $purchaseReturn->validateDocumentFile($file);
                if (!empty($validationErrors)) {
                    return ApiResponse::customError('Document validation failed: ' . implode(', ', $validationErrors), 422);
                }
            }

            $purchaseReturn->updateDocuments($files, [
                'type' => 'purchase_return_document'
            ]);
        }

        $purchaseReturn->load([
            'createdBy:id,name',
            'updatedBy:id,name',
            'supplier:id,code,name',
            'warehouse:id,name',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'purchaseReturnItems.item:id,code,short_name',
            'documents'
        ]);

        return ApiResponse::update(
            'Purchase return updated successfully',
            new PurchaseReturnResource($purchaseReturn)
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PurchaseReturn $purchaseReturn): JsonResponse
    {
        $purchaseReturn->delete();

        return ApiResponse::delete('Purchase return deleted successfully');
    }

    /**
     * Display a listing of trashed resources.
     */
    public function trashed(Request $request): JsonResponse
    {
        $query = PurchaseReturn::onlyTrashed()
            ->with([
                'createdBy:id,name',
                'updatedBy:id,name',
                'supplier:id,code,name',
                'warehouse:id,name',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            ])
            ->searchable($request)
            ->sortable($request);

        $purchaseReturns = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed purchase returns retrieved successfully',
            $purchaseReturns,
            PurchaseReturnResource::class
        );
    }

    /**
     * Restore the specified resource from trash.
     */
    public function restore(int $id): JsonResponse
    {
        $purchaseReturn = PurchaseReturn::onlyTrashed()->findOrFail($id);
        $purchaseReturn->restore();

        return ApiResponse::update('Purchase return restored successfully');
    }

    /**
     * Permanently delete the specified resource.
     */
    public function forceDelete(int $id): JsonResponse
    {
        $purchaseReturn = PurchaseReturn::onlyTrashed()->findOrFail($id);
        $purchaseReturn->forceDelete();

        return ApiResponse::delete('Purchase return permanently deleted successfully');
    }

    /**
     * Upload documents for a purchase return
     */
    public function uploadDocuments(Request $request, PurchaseReturn $purchaseReturn): JsonResponse
    {
        $request->validate([
            'documents' => 'required|array|max:15',
            'documents.*' => 'required|file|mimes:jpg,jpeg,png,gif,bmp,webp,pdf,doc,docx,txt|max:10240', // 10MB max
        ]);

        $files = $request->file('documents');

        // Validate each document file using the model's validation
        foreach ($files as $file) {
            $validationErrors = $purchaseReturn->validateDocumentFile($file);
            if (!empty($validationErrors)) {
                return ApiResponse::customError('Document validation failed: ' . implode(', ', $validationErrors), 422);
            }
        }

        // Upload documents
        $uploadedDocuments = $purchaseReturn->createDocuments($files, [
            'type' => 'purchase_return_document'
        ]);

        return ApiResponse::store(
            'Documents uploaded successfully',
            [
                'uploaded_count' => $uploadedDocuments->count(),
                'documents' => $uploadedDocuments->map(function ($doc) {
                    return [
                        'id' => $doc->id,
                        'original_name' => $doc->original_name,
                        'file_name' => $doc->file_name,
                        'thumbnail_url' => $doc->thumbnail_url,
                        'download_url' => $doc->download_url,
                    ];
                })
            ]
        );
    }

    /**
     * Delete specific documents for a purchase return
     */
    public function deleteDocuments(Request $request, PurchaseReturn $purchaseReturn): JsonResponse
    {
        $request->validate([
            'document_ids' => 'required|array',
            'document_ids.*' => 'required|integer|exists:documents,id',
        ]);

        // Verify that the documents belong to this purchase return
        $documentCount = $purchaseReturn->documents()
            ->whereIn('id', $request->document_ids)
            ->count();

        if ($documentCount !== count($request->document_ids)) {
            return ApiResponse::customError('Some documents do not belong to this purchase return', 403);
        }

        // Delete the documents
        $deleted = $purchaseReturn->deleteDocuments($request->document_ids);

        return ApiResponse::delete(
            'Documents deleted successfully',
            ['deleted_count' => $deleted ? count($request->document_ids) : 0]
        );
    }

    /**
     * Get documents for a specific purchase return
     */
    public function getDocuments(PurchaseReturn $purchaseReturn): JsonResponse
    {
        $documents = $purchaseReturn->documents()->get();

        return ApiResponse::show(
            'Purchase return documents retrieved successfully',
            $documents->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'original_name' => $doc->original_name,
                    'file_name' => $doc->file_name,
                    'file_size' => $doc->file_size,
                    'file_size_human' => $doc->file_size_human,
                    'thumbnail_url' => $doc->thumbnail_url,
                    'download_url' => $doc->download_url,
                    'uploaded_at' => $doc->created_at,
                ];
            })
        );
    }

    /**
     * Get statistics for purchase returns
     */
    public function stats(Request $request): JsonResponse
    {
        $query = $this->purchaseReturnQuery($request);

        $stats = [
            'purchase_returns_by_shipping_status' => (clone $query)->selectRaw('shipping_status, count(*) as count')
                ->whereNotNull('shipping_status')
                ->groupBy('shipping_status')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->shipping_status => $item->count];
                }),
        ];

        return ApiResponse::show('Purchase return statistics retrieved successfully', $stats);
    }

    public function changeStatus(Request $request, PurchaseReturn $purchaseReturn): JsonResponse
    {

        if (! RoleHelper::isWarehouseManager() && ! RoleHelper::isAdmin()) {
            return ApiResponse::customError('Only warehouse manager or admin can change the status.', 422);
        }

        $purchaseReturn->update(['shipping_status' => $request->shipping_status]);
        return ApiResponse::update(
            'PurchaseReturn status updated successfully',
            new PurchaseReturnResource($purchaseReturn)
        );
    }

    /**
     * Build the purchase return query with filters
     */
    private function purchaseReturnQuery(Request $request)
    {
        $query = PurchaseReturn::query()
            ->with([
                'createdBy:id,name',
                'updatedBy:id,name',
                'supplier:id,code,name',
                'warehouse:id,name',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            ])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        if ($request->has('currency_id')) {
            $query->where('currency_id', $request->input('currency_id'));
        }

        if ($request->has('code')) {
            $query->byCode($request->input('code'));
        }

        if ($request->has('supplier_purchase_return_number')) {
            $query->bySupplierPurchaseReturnNumber($request->input('supplier_purchase_return_number'));
        }

        if ($request->has('shipping_status')) {
            $query->byShippingStatus($request->input('shipping_status'));
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
