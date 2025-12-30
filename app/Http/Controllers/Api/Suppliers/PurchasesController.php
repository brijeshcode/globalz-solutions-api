<?php

namespace App\Http\Controllers\Api\Suppliers;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Suppliers\PurchasesStoreRequest;
use App\Http\Requests\Api\Suppliers\PurchasesUpdateRequest;
use App\Http\Resources\Api\Suppliers\PurchaseResource;
use App\Models\Suppliers\Purchase;
use App\Services\Suppliers\PurchaseService;
use App\Traits\HasPagination;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchasesController extends Controller
{
    use HasPagination;

    protected $purchaseService;

    public function __construct(PurchaseService $purchaseService)
    {
        $this->purchaseService = $purchaseService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->purchaseQuery($request);

        $purchases = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Purchases retrieved successfully',
            $purchases,
            PurchaseResource::class
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PurchasesStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $items = $data['items'] ?? [];
        unset($data['items']); // Remove items from purchase data

        // Create purchase with items using service
        $purchase = $this->purchaseService->createPurchaseWithItems($data, $items);

        // Handle document uploads
        if ($request->hasFile('documents')) {
            $files = $request->file('documents');
            if (!is_array($files)) {
                $files = [$files];
            }

            // Validate each document file
            foreach ($files as $file) {
                $validationErrors = $purchase->validateDocumentFile($file);
                if (!empty($validationErrors)) {
                    return ApiResponse::customError('Document validation failed: ' . implode(', ', $validationErrors), 422);
                }
            }

            // Upload documents
            $purchase->createDocuments($files, [
                'type' => 'purchase_document'
            ]);
        }

        $purchase->load([
            'createdBy:id,name',
            'updatedBy:id,name',
            'supplier:id,code,name',
            'warehouse:id,name',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            // 'account:id,name',
            'purchaseItems.item:id,code,short_name',
            'documents'
        ]);

        return ApiResponse::store(
            'Purchase created successfully',
            new PurchaseResource($purchase)
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Purchase $purchase): JsonResponse
    {
        $purchase->load([
            'createdBy:id,name',
            'updatedBy:id,name',
            'supplier:id,code,name',
            'warehouse:id,name',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            // 'account:id,name',
            'purchaseItems.item:id,code,short_name',
            'documents'
        ]);

        return ApiResponse::show(
            'Purchase retrieved successfully',
            new PurchaseResource($purchase)
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PurchasesUpdateRequest $request, Purchase $purchase): JsonResponse
    {
        $data = $request->validated();
        $items = $data['items'] ?? [];
        unset($data['items']); // Remove items from purchase data
        unset($data['code']); // Remove code from data if present (code is system generated only, not updatable)
        // Update purchase with items using service
        $purchase = $this->purchaseService->updatePurchaseWithItems($purchase, $data, $items);

        // Handle document uploads
        if ($request->hasFile('documents')) {
            $files = $request->file('documents');
            if (!is_array($files)) {
                $files = [$files];
            }

            // Validate each document file
            foreach ($files as $file) {
                $validationErrors = $purchase->validateDocumentFile($file);
                if (!empty($validationErrors)) {
                    return ApiResponse::customError('Document validation failed: ' . implode(', ', $validationErrors), 422);
                }
            }

            $purchase->updateDocuments($files, [
                'type' => 'purchase_document'
            ]);
        }

        $purchase->load([
            'createdBy:id,name',
            'updatedBy:id,name',
            'supplier:id,code,name',
            'warehouse:id,name',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            // 'account:id,name',
            'purchaseItems.item:id,code,short_name',
            'documents'
        ]);

        return ApiResponse::update(
            'Purchase updated successfully',
            new PurchaseResource($purchase)
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Purchase $purchase): JsonResponse
    {
        $this->purchaseService->deletePurchase($purchase);

        return ApiResponse::delete('Purchase deleted successfully');
    }

    /**
     * Display a listing of trashed resources.
     */
    public function trashed(Request $request): JsonResponse
    {
        $query = Purchase::onlyTrashed()
            ->with([
                'createdBy:id,name',
                'updatedBy:id,name',
                'supplier:id,code,name',
                'warehouse:id,name',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                // 'account:id,name'
            ])
            ->searchable($request)
            ->sortable($request);

        $purchases = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed purchases retrieved successfully',
            $purchases,
            PurchaseResource::class
        );
    }

    public function changeStatus(Request $request, Purchase $purchase): JsonResponse
    {
        if (! RoleHelper::canWarehouseManager()) {
            return ApiResponse::customError('Only warehouse manager can change the status.', 422);
        }

        // Validate status
        $request->validate([
            'status' => 'required|string|in:Waiting,Shipped,Delivered'
        ]);

        // Prevent status change if already delivered
        if ($purchase->status === 'Delivered') {
            return ApiResponse::customError('Cannot change status. Purchase is already delivered and inventory has been added.', 422);
        }

        // If changing to Delivered, use the deliverPurchase service method
        if ($request->status === 'Delivered') {
            try {
                $this->purchaseService->deliverPurchase($purchase);

                $purchase->load([
                    'createdBy:id,name',
                    'updatedBy:id,name',
                    'supplier:id,code,name',
                    'warehouse:id,name',
                    'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                    'purchaseItems.item:id,code,short_name',
                    'documents'
                ]);

                return ApiResponse::update(
                    'Purchase delivered successfully. Inventory has been updated.',
                    new PurchaseResource($purchase)
                );
            } catch (\InvalidArgumentException $e) {
                return ApiResponse::customError($e->getMessage(), 422);
            } catch (\Exception $e) {
                return ApiResponse::customError('Failed to deliver purchase: ' . $e->getMessage(), 500);
            }
        }

        // For other status changes (Waiting -> Shipped, etc.)
        $purchase->update(['status' => $request->status]);

        $purchase->load([
            'createdBy:id,name',
            'updatedBy:id,name',
            'supplier:id,code,name',
            'warehouse:id,name',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'purchaseItems.item:id,code,short_name',
            'documents'
        ]);

        return ApiResponse::update(
            'Purchase status updated successfully',
            new PurchaseResource($purchase)
        );
    }


  
    /**
     * Get the next suggested purchase code
     */
    public function getNextCode(): JsonResponse
    {
        $nextCode = Purchase::getNextSuggestedCode();

        return ApiResponse::show('Next purchase code retrieved successfully', [
            'code' => $nextCode,
            'is_available' => true,
            'message' => 'Next available code'
        ]);
    }

    /**
     * Upload documents for a purchase
     */
    public function uploadDocuments(Request $request, Purchase $purchase): JsonResponse
    {
        $request->validate([
            'documents' => 'required|array|max:15',
            'documents.*' => 'required|file|mimes:jpg,jpeg,png,gif,bmp,webp,pdf,doc,docx,txt|max:10240', // 10MB max
        ]);

        $files = $request->file('documents');

        // Validate each document file using the model's validation
        foreach ($files as $file) {
            $validationErrors = $purchase->validateDocumentFile($file);
            if (!empty($validationErrors)) {
                return ApiResponse::customError('Document validation failed: ' . implode(', ', $validationErrors), 422);
            }
        }

        // Upload documents
        $uploadedDocuments = $purchase->createDocuments($files, [
            'type' => 'purchase_document'
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
     * Delete specific documents for a purchase
     */
    public function deleteDocuments(Request $request, Purchase $purchase): JsonResponse
    {
        $request->validate([
            'document_ids' => 'required|array',
            'document_ids.*' => 'required|integer|exists:documents,id',
        ]);

        // Verify that the documents belong to this purchase
        $documentCount = $purchase->documents()
            ->whereIn('id', $request->document_ids)
            ->count();

        if ($documentCount !== count($request->document_ids)) {
            return ApiResponse::customError('Some documents do not belong to this purchase', 403);
        }

        // Delete the documents
        $deleted = $purchase->deleteDocuments($request->document_ids);

        return ApiResponse::delete(
            'Documents deleted successfully',
            ['deleted_count' => $deleted ? count($request->document_ids) : 0]
        );
    }

    /**
     * Get documents for a specific purchase
     */
    public function getDocuments(Purchase $purchase): JsonResponse
    {
        $documents = $purchase->documents()->get();

        return ApiResponse::show(
            'Purchase documents retrieved successfully',
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

    public function stats(Request $request): JsonResponse
    {
        $query = $this->purchaseQuery($request);

        $stats = [
            'purchase_by_status' => (clone $query)->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->status => $item->count];
                }),
            'total_purchase' => (clone $query)->sum('final_total_usd'),
        ];

        return ApiResponse::show('Sale statistics retrieved successfully', $stats);
    }

    private function purchaseQuery(Request $request)
    {
        $query = Purchase::query()
            ->with([
                'createdBy:id,name',
                'updatedBy:id,name',
                'supplier:id,code,name',
                'warehouse:id,name',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                // 'account:id,name'
            ])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        if ($request->has('currency_id')) {
            $query->where('currency_id', $request->input('currency_id'));
        }

        // if ($request->has('account_id')) {
        //     $query->where('account_id', $request->input('account_id'));
        // }

        if ($request->has('code')) {
            $query->byCode($request->input('code'));
        }

        if ($request->has('supplier_invoice_number')) {
            $query->bySupplierInvoiceNumber($request->input('supplier_invoice_number'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('from_date')) {
            $query->where('date', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('date', '<=', $request->to_date);
        }

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

        return $query;
    }
}
