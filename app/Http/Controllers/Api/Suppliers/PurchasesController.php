<?php

namespace App\Http\Controllers\Api\Suppliers;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Suppliers\PurchasesStoreRequest;
use App\Http\Requests\Api\Suppliers\PurchasesUpdateRequest;
use App\Http\Resources\Api\Suppliers\PurchaseResource;
use App\Models\Customers\Sale;
use App\Models\Items\Item;
use App\Models\Suppliers\Purchase;
use App\Services\Customers\SaleProfitRecalculationService;
use App\Services\Suppliers\PurchaseService;
use App\Traits\HasPagination;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchasesController extends Controller
{
    use HasPagination;

    protected PurchaseService $purchaseService;

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
        try {
            $data = $request->validated();
            $items    = $data['items'] ?? [];
            $expenses = $data['expenses'] ?? [];
            unset($data['items'], $data['expenses']);

            // Remove auto-calculated fields if present (these are calculated by the service)
            unset($data['sub_total'], $data['sub_total_usd'], $data['total'], $data['total_usd'], $data['final_total'], $data['final_total_usd']);

            // Create purchase with items using service
            $purchase = $this->purchaseService->createPurchaseWithItems($data, $items, $expenses);

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
                'purchaseExpenses.expenseTransaction.expenseCategory:id,name',
                'purchaseExpenses.expenseTransaction.payments',
                'purchaseExpenses.expenseTransaction.account:id,name',
                'documents'
            ]);

            return ApiResponse::store(
                'Purchase created successfully',
                new PurchaseResource($purchase)
            );
        } catch (\InvalidArgumentException $e) {
            // Validation errors (inventory issues, etc.) - user-friendly messages
            return ApiResponse::customError($e->getMessage(), 422);
        } catch (\Exception $e) {
            // System/unexpected errors
            return ApiResponse::customError('Failed to create purchase: ' . $e->getMessage(), 500);
        }
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
            'purchaseItems.item:id,code,short_name',
            'purchaseExpenses.expenseTransaction.expenseCategory:id,name',
            'purchaseExpenses.expenseTransaction.payments',
            'purchaseExpenses.expenseTransaction.account:id,name',
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
        try {
            $data = $request->validated();
            $items    = $data['items'] ?? [];
            $expenses = $data['expenses'] ?? [];
            unset($data['items'], $data['expenses'], $data['code']);

            // Remove auto-calculated fields if present (these are calculated by the service)
            unset($data['sub_total'], $data['sub_total_usd'], $data['total'], $data['total_usd'], $data['final_total'], $data['final_total_usd']);

            // Update purchase with items using service
            $purchase = $this->purchaseService->updatePurchaseWithItems($purchase, $data, $items, $expenses);

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
                'purchaseExpenses.expenseTransaction.expenseCategory:id,name',
                'purchaseExpenses.expenseTransaction.payments',
                'purchaseExpenses.expenseTransaction.account:id,name',
                'documents'
            ]);

            return ApiResponse::update(
                'Purchase updated successfully',
                new PurchaseResource($purchase)
            );
        } catch (\InvalidArgumentException $e) {
            // Validation errors (inventory issues, etc.) - user-friendly messages
            return ApiResponse::customError($e->getMessage(), 422);
        } catch (\Exception $e) {
            // System/unexpected errors
            return ApiResponse::customError('Failed to update purchase: ' . $e->getMessage(), 500);
        }
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
                    'purchaseExpenses.expenseTransaction.expenseCategory:id,name',
                    'purchaseExpenses.expenseTransaction.payments',
                    'purchaseExpenses.expenseTransaction.account:id,name',
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
        $purchase->deleteDocuments($request->document_ids);

        return ApiResponse::delete('Documents deleted successfully');
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

    /**
     * Preview what a profit recalculation would change — computed fresh, nothing stored.
     * Grouped per sale (code, old/new profit) with item details, purely for review:
     * the confirm endpoint always applies ALL changes, recomputed at execution time.
     */
    public function recalculateSaleProfitPreview(Purchase $purchase, SaleProfitRecalculationService $service): JsonResponse
    {
        if ($purchase->status !== 'Delivered') {
            return ApiResponse::customError('Cannot recalculate profit. Purchase is not delivered yet.', 422);
        }

        $changes = $service->buildChangesForPurchase($purchase);

        $sales = Sale::whereIn('id', array_unique(array_column($changes, 'sale_id')))
            ->get(['id', 'prefix', 'code', 'date', 'total_profit'])
            ->keyBy('id');

        $items = Item::whereIn('id', array_unique(array_column($changes, 'item_id')))
            ->get(['id', 'code', 'short_name', 'description'])
            ->keyBy('id');

        $salesPreview      = [];
        $totalProfitChange = 0.0;

        foreach ($changes as $change) {
            $saleId = $change['sale_id'];
            $sale   = $sales->get($saleId);
            $item   = $items->get($change['item_id']);
            $delta  = $change['new_total_profit'] - $change['old_total_profit'];

            if (!isset($salesPreview[$saleId])) {
                $oldProfit = (float) ($sale?->total_profit ?? 0);
                $salesPreview[$saleId] = [
                    'sale_id'          => $saleId,
                    'sale_code'        => $sale ? $sale->prefix . $sale->code : null,
                    'sale_date'        => $change['sale_date'],
                    'old_total_profit' => $oldProfit,
                    'new_total_profit' => $oldProfit, // item deltas applied below
                    'items'            => [],
                ];
            }

            $salesPreview[$saleId]['new_total_profit'] += $delta;
            $totalProfitChange                         += $delta;

            $salesPreview[$saleId]['items'][] = [
                'sale_item_id'     => $change['sale_item_id'],
                'item_id'          => $change['item_id'],
                'item_code'        => $item?->code,
                'item_name'        => $item?->short_name,
                'item_description' => $item?->description,
                'quantity'         => $change['quantity'],
                'old_cost'         => $change['old_cost'],
                'new_cost'         => $change['new_cost'],
                'old_total_profit' => $change['old_total_profit'],
                'new_total_profit' => $change['new_total_profit'],
            ];
        }

        foreach ($salesPreview as &$salePreview) {
            $salePreview['profit_change'] = $salePreview['new_total_profit'] - $salePreview['old_total_profit'];
        }
        unset($salePreview);

        return ApiResponse::send('Recalculation preview generated.', 200, [
            'summary' => [
                'purchase_id'          => $purchase->id,
                'purchase_date'        => $purchase->date,
                'sale_items_to_update' => count($changes),
                'sales_affected'       => count($salesPreview),
                'total_profit_change'  => $totalProfitChange,
            ],
            'sales' => array_values($salesPreview),
        ]);
    }

    public function recalculateSaleProfit(Purchase $purchase, SaleProfitRecalculationService $service): JsonResponse
    {
        if ($purchase->status !== 'Delivered') {
            return ApiResponse::customError('Cannot recalculate profit. Purchase is not delivered yet.', 422);
        }

        $result = $service->recalculateForPurchase($purchase);

        return ApiResponse::send('Sale profit recalculated.', 200, $result);
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

        if ($request->has('prefix')) {
            $query->byPrefix($request->prefix);
        }

        if ($request->has('supplier_invoice_number')) {
            $query->bySupplierInvoiceNumber($request->input('supplier_invoice_number'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('from_date')) {
            $query->fromDate($request->from_date);
        } 
        
        if ($request->has('to_date')) {
            $query->toDate( $request->to_date);
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
