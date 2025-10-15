<?php

namespace App\Http\Controllers\Api\Items;

use App\Facades\Settings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Items\ItemsStoreRequest;
use App\Http\Requests\Api\Items\ItemsUpdateRequest;
use App\Http\Requests\Api\Items\ItemsImportRequest;
use App\Http\Resources\Api\Items\ItemListResource;
use App\Http\Resources\Api\Items\ItemResource;
use App\Http\Responses\ApiResponse;
use App\Imports\ItemsImport;
use App\Models\Items\Item;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class ItemsController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = Item::query()
            ->with([
                'itemType:id,name',
                'itemFamily:id,name',
                'itemGroup:id,name',
                'itemCategory:id,name',
                'itemBrand:id,name',
                'itemProfitMargin:id,name',
                'itemUnit:id,name,short_name',
                'supplier:id,code,name',
                'taxCode:id,name,tax_percent',
                'createdBy:id,name',
                'updatedBy:id,name',
                'documents',
                'inventories',
                'itemPrice'
            ])
            ->searchable($request)
            ->sortable($request);

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by item type
        if ($request->has('item_type_id')) {
            $query->where('item_type_id', $request->item_type_id);
        }

        // Filter by item family
        if ($request->has('item_family_id')) {
            $query->where('item_family_id', $request->item_family_id);
        }

        // Filter by item group
        if ($request->has('item_group_id')) {
            $query->where('item_group_id', $request->item_group_id);
        }

        // Filter by item category
        if ($request->has('item_category_id')) {
            $query->where('item_category_id', $request->item_category_id);
        }

        // Filter by item brand
        if ($request->has('item_brand_id')) {
            $query->where('item_brand_id', $request->item_brand_id);
        }

        // Filter by supplier
        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // Filter by tax code
        if ($request->has('tax_code_id')) {
            $query->where('tax_code_id', $request->tax_code_id);
        }

        // Price range filters
        if ($request->has('min_price')) {
            $query->where('base_sell', '>=', $request->min_price);
        }

        if ($request->has('max_price')) {
            $query->where('base_sell', '<=', $request->max_price);
        }

        // Cost range filters
        if ($request->has('min_cost')) {
            $query->where('base_cost', '>=', $request->min_cost);
        }

        if ($request->has('max_cost')) {
            $query->where('base_cost', '<=', $request->max_cost);
        }

        // Low stock filter
        if ($request->boolean('low_stock')) {
            $query->lowStock();
        }

        // Filter by cost calculation method
        if ($request->has('cost_calculation')) {
            $query->where('cost_calculation', $request->cost_calculation);
        }

        // Filter by warehouse - show only items that have inventory in selected warehouse
        if ($request->has('warehouse_id')) {
            $warehouseId = $request->warehouse_id;
            $query->whereHas('inventories', function ($inventoryQuery) use ($warehouseId) {
                $inventoryQuery->where('warehouse_id', $warehouseId);
            });
            
            // Add warehouse-specific inventory quantity
            $query->addSelect([
                'warehouse_inventory_quantity' => \App\Models\Inventory\Inventory::selectRaw('COALESCE(quantity, 0)')
                    ->whereColumn('item_id', 'items.id')
                    ->where('warehouse_id', $warehouseId)
                    ->limit(1)
            ]);
        }

        $items = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Items retrieved successfully',
            $items,
            ItemResource::class
        );
    }

    public function store(ItemsStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        
        // Handle code generation
        if (!isset($data['code']) || empty($data['code'])) {
            // Auto-generate code if not provided
            $data['code'] = $this->generateItemCode();
        }

        // Create item in a transaction to handle all related operations
        $item = DB::transaction(function () use ($data, $request) {
            // Create the item
            $item = Item::create($data);
            
            // Initialize inventory if starting_quantity is provided
            if (isset($data['starting_quantity']) && $data['starting_quantity'] > 0) {
                $this->initializeInventory($item, $data['starting_quantity']);
            }
            // Initialize supplier item price if base_cost and supplier_id are provided
            if (isset($data['base_cost']) && $data['base_cost'] > 0 && isset($data['supplier_id'])) {
                $this->initializeSupplierItemPrice($item, $data['supplier_id']);
            }
            
            return $item;
        });
        
        // Increment counter only after successful creation
        if (!$request->has('code') || empty($request->input('code'))) {
            // Auto-generated code - increment counter
            Settings::incrementItemCode();
        } else {
            // Custom code using current counter - increment if matches
            $numericPart = $this->extractNumericPart($data['code']);
            $currentCounter = (int) Settings::get('items', 'code_counter', 5000, true, 'number');
            if ($numericPart && (int) $numericPart === $currentCounter) {
                Settings::incrementItemCode();
            }
        }

        // Handle image uploads
        if ($request->hasFile('images')) {
            $files = $request->file('images');
            if (!is_array($files)) {
                $files = [$files];
            }
            
            // Validate each image file
            foreach ($files as $file) {
                $validationErrors = $item->validateDocumentFile($file);
                if (!empty($validationErrors)) {
                    return ApiResponse::customError('Image validation failed: ' . implode(', ', $validationErrors), 422);
                }
            }
            
            // Upload images
            $item->createDocuments($files, [
                'type' => 'image'
            ]);
        }

        $item->load([
            'itemType:id,name',
            'itemFamily:id,name',
            'itemGroup:id,name',
            'itemCategory:id,name',
            'itemBrand:id,name',
            'itemProfitMargin:id,name',
            'itemUnit:id,name',
            'supplier:id,code,name',
            'taxCode:id,name,tax_percent',
            'createdBy:id,name',
            'updatedBy:id,name',
            'documents'
        ]);

        return ApiResponse::store(
            'Item created successfully',
            new ItemResource($item)
        );
    }

    public function show(Item $item): JsonResponse
    {
        $item->load([
            'itemType:id,name',
            'itemFamily:id,name',
            'itemGroup:id,name',
            'itemCategory:id,name',
            'itemBrand:id,name',
            'itemUnit:id,name',
            'itemProfitMargin:id,name',
            'supplier:id,code,name',
            'taxCode:id,name,tax_percent',
            'createdBy:id,name',
            'updatedBy:id,name',
            'documents'
        ]);

        return ApiResponse::show(
            'Item retrieved successfully',
            new ItemResource($item)
        );
    }

    public function update(ItemsUpdateRequest $request, Item $item): JsonResponse
    {
        $data = $request->validated();

        $item->update($data);
        // Handle image uploads
        if ($request->hasFile('documents')) {
            $files = $request->file('documents');
            if (!is_array($files)) {
                $files = [$files];
            }
            
            // Validate each image file
            foreach ($files as $file) {
                $validationErrors = $item->validateDocumentFile($file);
                if (!empty($validationErrors)) {
                    return ApiResponse::customError('Image validation failed: ' . implode(', ', $validationErrors), 422);
                }
            }
            $item->updateDocuments($files, [
                'type' => 'image'
            ]);
        }


        $item->load([
            'itemType:id,name',
            'itemFamily:id,name',
            'itemGroup:id,name',
            'itemCategory:id,name',
            'itemBrand:id,name',
            'itemProfitMargin:id,name',
            'itemUnit:id,name',
            'supplier:id,code,name',
            'taxCode:id,name,tax_percent',
            'createdBy:id,name',
            'updatedBy:id,name',
            'documents'
        ]);

        return ApiResponse::update(
            'Item updated successfully',
            new ItemResource($item)
        );
    }

    public function destroy(Item $item): JsonResponse
    {
        if ($item->hasDocuments()) {
            $item->deleteDocuments(); // Soft delete all documents
        }
        
        $item->delete();

        return ApiResponse::delete('Item deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = Item::onlyTrashed()
            ->with([
                'itemType:id,name',
                'itemFamily:id,name',
                'itemGroup:id,name',
                'itemCategory:id,name',
                'itemBrand:id,name',
                'itemProfitMargin:id,name',
                'itemUnit:id,name,short_name',
                'supplier:id,code,name',
                'taxCode:id,name,tax_percent',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
            ->searchable($request)
            ->sortable($request);

        // Apply same filters as index method
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('item_type_id')) {
            $query->where('item_type_id', $request->item_type_id);
        }

        $items = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed items retrieved successfully',
            $items,
            ItemResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $item = Item::onlyTrashed()->findOrFail($id);
        
        // Restore item first
        $item->restore();
        
        // Restore associated documents (images) if any exist
        $item->restoreDocuments(); // Restore all soft-deleted documents for this item
        
        $item->load([
            'itemType:id,name',
            'itemFamily:id,name',
            'itemGroup:id,name',
            'itemCategory:id,name',
            'itemBrand:id,name',
            'itemProfitMargin:id,name',
            'itemUnit:id,name',
            'supplier:id,code,name',
            'taxCode:id,name,tax_percent',
            'createdBy:id,name',
            'updatedBy:id,name',
            'documents'
        ]);

        return ApiResponse::update(
            'Item restored successfully',
            new ItemResource($item)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $item = Item::onlyTrashed()->findOrFail($id);
        
        // Force delete associated documents (permanently delete files and records)
        $item->forceDeleteDocuments(); // This deletes physical files and database records
        
        // Permanently delete item
        $item->forceDelete();

        return ApiResponse::delete('Item permanently deleted successfully');
    }

    /**
     * Get the next suggested item code
     */
    public function getNextCode(): JsonResponse
    {
        $nextCode = $this->getLatestItemCode();
        
        return ApiResponse::show('Next item code retrieved successfully', [
            'code' => $nextCode,
            'is_available' => true,
            'message' => 'You can use this code as-is or customize it with prefix/suffix (e.g., ' . $nextCode . 'A, PREFIX-' . $nextCode . ')'
        ]);
    }

    /**
     * Check if a specific code is available
     */
    public function checkCodeAvailability(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|max:255'
        ]);

        $code = $request->input('code');
        $isAvailable = $this->isCodeAvailable($code);
        $numericPart = $this->extractNumericPart($code);

        if ($isAvailable) {
            return ApiResponse::show('Code is available', [
                'code' => $code,
                'is_available' => true,
                'numeric_part' => $numericPart,
                'message' => 'This code is available for use'
            ]);
        } else {
            $suggestedCode = $this->getLatestItemCode();
            return ApiResponse::show('Code is not available', [
                'code' => $code,
                'is_available' => false,
                'numeric_part' => $numericPart,
                'suggested_code' => $suggestedCode,
                'message' => 'This code is already taken. Try: ' . $suggestedCode
            ]);
        }
    }

    /**
     * Generate unique item code starting from settings
    */
    private function generateItemCode(): string
    {
        return Settings::getCurrentItemCode();
    }

    /**
     * Get the latest available item code (for real-time updates)
     */
    private function getLatestItemCode(): string
    {
        // Get current counter (don't increment yet)
        $currentCounter = (int) Settings::get('items', 'code_counter', 5000, true, 'number');
        
        return (string) $currentCounter;
    }

    /**
     * Check if code is available
     */
    private function isCodeAvailable(string $code): bool
    {
        // First check for exact duplicate
        $exactMatch = Item::withTrashed()->where('code', $code)->exists();
        if ($exactMatch) {
            return false; // Code already exists
        }

        // For codes with numeric parts, apply counter validation
        $numericPart = $this->extractNumericPart($code);
        if ($numericPart) {
            $currentCounter = (int) Settings::get('items', 'code_counter', 5000, true, 'number');
            return (int) $numericPart >= $currentCounter;
        }

        // Non-numeric codes are available if not duplicates
        return true;
    }

    /**
     * Extract numeric part from code (handles prefix/suffix)
     */
    private function extractNumericPart(string $code): ?string
    {
        // Extract all numeric sequences and concatenate them
        if (preg_match_all('/(\d+)/', $code, $matches)) {
            // Concatenate all numeric parts to form one number
            return implode('', $matches[1]);
        }
        return null;
    }

    /**
     * Initialize inventory for a new item with starting quantity
     */
    private function initializeInventory(Item $item, float $startingQuantity): void
    {
        // Get default warehouse or create one if none exists
        $defaultWarehouse = \App\Models\Setups\Warehouse::where('is_default', true)->first();
        
        if (!$defaultWarehouse) {
            // If no default warehouse exists, get the first available warehouse
            $defaultWarehouse = \App\Models\Setups\Warehouse::first();
        }
        
        if ($defaultWarehouse) {
            \App\Services\Inventory\InventoryService::set(
                $item->id,
                $defaultWarehouse->id,
                (int) $startingQuantity,
                'Initial inventory from item creation'
            );
        }
    }

    /**
     * Initialize supplier item price for a new item
     */
    private function initializeSupplierItemPrice(Item $item, int $supplierId): void
    {
        \App\Services\Suppliers\SupplierItemPriceService::initializeFromItem(
            $supplierId,
            $item
        );
    }


    /**
     * Get item statistics for dashboard
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_items' => Item::count(),
            'active_items' => Item::where('is_active', true)->count(),
            'inactive_items' => Item::where('is_active', false)->count(),
            'trashed_items' => Item::onlyTrashed()->count(),
            'low_stock_items' => Item::whereRaw('starting_quantity <= low_quantity_alert')
                ->whereNotNull('low_quantity_alert')
                ->count(),
            'items_with_stock' => Item::whereHas('inventories', function ($query) {
                $query->where('quantity', '>', 0);
            })->count(),
            // 'total_starting_quantity' => Item::sum('starting_quantity'),
            // 'total_inventory_quantity' => DB::table('inventories')->sum('quantity'),
            // 'total_net_quantity' => Item::sum('starting_quantity') + DB::table('inventories')->sum('quantity'),
            // 'total_inventory_value' => Item::selectRaw('SUM(starting_quantity * base_cost) as total')->value('total') ?? 0,
            // 'total_warehouse_inventory_value' => DB::table('inventories')
            //     ->join('items', 'inventories.item_id', '=', 'items.id')
            //     ->selectRaw('SUM(inventories.quantity * items.base_cost) as total')
            //     ->value('total') ?? 0,
            // 'items_by_type' => Item::with('itemType:id,name')
            //     ->selectRaw('item_type_id, count(*) as count')
            //     ->groupBy('item_type_id')
            //     ->having('count', '>', 0)
            //     ->get(),
            // 'items_by_family' => Item::with('itemFamily:id,name')
            //     ->selectRaw('item_family_id, count(*) as count')
            //     ->groupBy('item_family_id')
            //     ->having('count', '>', 0)
            //     ->get(),
            // 'cost_calculation_breakdown' => Item::selectRaw('cost_calculation, count(*) as count')
            //     ->groupBy('cost_calculation')
            //     ->get(),
            // 'inventory_by_warehouse' => DB::table('inventories')
            //     ->join('warehouses', 'inventories.warehouse_id', '=', 'warehouses.id')
            //     ->selectRaw('warehouses.name as warehouse_name, SUM(inventories.quantity) as total_quantity')
            //     ->groupBy('warehouses.id', 'warehouses.name')
            //     ->get(),
        ];

        return ApiResponse::show('Item statistics retrieved successfully', $stats);
    }

    /**
     * Export items for reports
     */
    public function export(Request $request): JsonResponse
    {
        $query = Item::query()
            ->with([
                'itemType:id,name',
                'itemFamily:id,name',
                'itemGroup:id,name',
                'itemCategory:id,name',
                'itemProfitMargin:id,name',
                'itemBrand:id,name',
                'itemUnit:id,name,short_name',
                'supplier:id,code,name',
                'taxCode:id,name,tax_percent'
            ])
            ->select([
                'id', 'code', 'short_name', 'description', 'item_type_id', 
                'item_family_id', 'item_group_id', 'item_category_id', 
                'item_brand_id', 'item_unit_id', 'supplier_id', 'tax_code_id',
                'base_cost', 'base_sell', 'starting_price', 'starting_quantity', 
                'low_quantity_alert', 'cost_calculation', 'is_active'
            ]);

        // Apply filters from request
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('item_type_id')) {
            $query->where('item_type_id', $request->item_type_id);
        }

        $items = $query->get();

        $exportData = $items->map(function ($item) {
            return [
                'code' => $item->code,
                'short_name' => $item->short_name,
                'description' => $item->description,
                'item_type' => $item->itemType?->name,
                'item_family' => $item->itemFamily?->name,
                'item_group' => $item->itemGroup?->name,
                'item_category' => $item->itemCategory?->name,
                'item_brand' => $item->itemBrand?->name,
                'item_profit_margin' => $item->itemProfitMargin?->name,
                'item_unit' => $item->itemUnit?->name,
                'supplier' => $item->supplier?->name,
                'tax_code' => $item->taxCode?->name,
                'base_cost' => $item->base_cost,
                'base_sell' => $item->base_sell,
                'starting_price' => $item->starting_price,
                'starting_quantity' => $item->starting_quantity,
                'low_quantity_alert' => $item->low_quantity_alert,
                'cost_calculation' => $item->cost_calculation,
                'status' => $item->is_active ? 'Active' : 'Inactive',
            ];
        });

        return ApiResponse::show('Items exported successfully', $exportData);
    }

    /**
     * Upload images for an item
     */
    public function uploadImages(Request $request, Item $item): JsonResponse
    {
        $request->validate([
            'images' => 'required|array|max:10',
            'images.*' => 'required|file|image|mimes:jpg,jpeg,png,gif,bmp,webp|max:5120', // 5MB max
        ]);

        $files = $request->file('images');
        
        // Validate each image file using the model's validation
        foreach ($files as $file) {
            $validationErrors = $item->validateDocumentFile($file);
            if (!empty($validationErrors)) {
                return ApiResponse::customError('Image validation failed: ' . implode(', ', $validationErrors), 422);
            }
        }
        
        // Upload images
        $uploadedDocuments = $item->createDocuments($files, [
            'type' => 'image'
        ]);

        return ApiResponse::store(
            'Images uploaded successfully',
            [
                'uploaded_count' => $uploadedDocuments->count(),
                'images' => $uploadedDocuments->map(function ($doc) {
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

    public function getAllItems(array $files = [] , array $relations = ['tax_code']): JsonResponse
    {
        $defaultFields = ['id', 'description', 'code', 'short_name', 'item_unit_id'];
        $defaultRelations = ['itemUnit:id,name,short_name', 'itemPrice', 'inventories'];

        $fields = empty($files) ? $defaultFields : array_merge($defaultFields, $files);

        $with = $defaultRelations;

        if (!empty($relations)) {
            foreach ($relations as $relation) {
                switch ($relation) {
                    case 'type':
                        $with[] = 'itemType:id,name';
                        break;
                    case 'family':
                        $with[] = 'itemFamily:id,name';
                        break;
                    case 'group':
                        $with[] = 'itemGroup:id,name';
                        break;
                    case 'category':
                        $with[] = 'itemCategory:id,name';
                        break;
                    case 'brand':
                        $with[] = 'itemBrand:id,name';
                        break;
                    case 'profit_margin':
                        $with[] = 'itemProfitMargin:id,name';
                        break;
                    case 'supplier':
                        $with[] = 'supplier:id,code,name';
                        break;
                    case 'tax_code':
                        $fields[] = 'tax_code_id';
                        $with[] = 'taxCode:id,name,tax_percent';
                        break;
                    case 'created_by':
                        $with[] = 'createdBy:id,name';
                        break;
                    case 'updated_by':
                        $with[] = 'updatedBy:id,name';
                        break;
                    case 'documents':
                        $with[] = 'documents';
                        break;
                }
            }
        }
        // info($fields);
        $items = Item::select($fields)->with($with)->active()->get();

        return ApiResponse::index(
            'Items retrieved successfully',
            ItemResource::collection($items)
        );
    }

    /**
     * Delete specific images for an item
     */
    public function deleteImages(Request $request, Item $item): JsonResponse
    {
        $request->validate([
            'image_ids' => 'required|array',
            'image_ids.*' => 'required|integer|exists:documents,id',
        ]);

        // Verify that the documents belong to this item
        $imageCount = $item->documents()
            ->whereIn('id', $request->image_ids)
            ->count();

        if ($imageCount !== count($request->image_ids)) {
            return ApiResponse::customError('Some images do not belong to this item', 403);
        }

        // Delete the images
        $deleted = $item->deleteDocuments($request->image_ids);

        return ApiResponse::delete(
            'Images deleted successfully',
            ['deleted_count' => $deleted ? count($request->image_ids) : 0]
        );
    }

    /**
     * Get images for a specific item
     */
    public function getImages(Item $item): JsonResponse
    {
        $images = $item->documents()->images()->get();

        return ApiResponse::show(
            'Item images retrieved successfully',
            $images->map(function ($doc) {
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
     * Import items from CSV/Excel file
     */
    public function import(ItemsImportRequest $request): JsonResponse
    {
        try {
            $file = $request->file('file');
            $skipDuplicates = $request->boolean('skip_duplicates', true);
            $updateExisting = $request->boolean('update_existing', true);

            // Create import instance
            $import = new ItemsImport($skipDuplicates, $updateExisting);

            // Process the import
            Excel::import($import, $file);

            // Get results
            $results = $import->getResults();

            // Determine response status
            if ($results['imported'] === 0 && $results['updated'] === 0) {
                Log::warning('No items imported', [
                    'total_rows' => $results['total'],
                    'skipped' => $results['skipped'],
                    'errors' => $results['errors']
                ]);

                return ApiResponse::customError(
                    'No items were imported. Please check the file format and data.',
                    422,
                    $results
                );
            }

            return ApiResponse::store(
                'Items import completed successfully',
                $results
            );

        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $errors = [];

            foreach ($failures as $failure) {
                $errors[] = [
                    'row' => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors' => $failure->errors(),
                    'values' => $failure->values()
                ];
            }

            Log::error('Item Import Validation Failed', ['errors' => $errors]);

            return ApiResponse::customError('Import validation failed', 422, [
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            Log::error('Item Import Exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return ApiResponse::customError(
                'Import failed: ' . $e->getMessage(),
                500,
                [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            );
        }
    }

    /**
     * Download sample Excel/CSV template for item import
     */
    public function downloadTemplate(Request $request)
    {
        $format = $request->query('format', 'xlsx'); // Default to xlsx

        $headers = [
            'code',
            'short_name',
            'description',
            'item_type',
            'item_family',
            'item_group',
            'item_category',
            'item_brand',
            'item_unit',
            'item_profit_margin',
            'supplier',
            'tax_code',
            'volume',
            'weight',
            'barcode',
            'base_cost',
            'base_sell',
            'starting_price',
            'starting_quantity',
            'low_quantity_alert',
            'cost_calculation',
            'notes',
            'is_active'
        ];

        $sampleData = [
            [
                '',  // code - leave empty for auto-generation
                'Sample Product',
                'Sample Product Description',
                'Finished Goods',  // item_type
                'Electronics',     // item_family
                'Computers',       // item_group
                'Laptops',         // item_category
                'Dell',            // item_brand
                'PCS',             // item_unit
                'Standard',        // item_profit_margin
                'SUP001',          // supplier code
                'VAT 11%',         // tax_code
                0.5,               // volume
                2.5,               // weight
                '1234567890123',   // barcode
                500.00,            // base_cost
                750.00,            // base_sell
                750.00,            // starting_price
                100,               // starting_quantity
                10,                // low_quantity_alert
                'weighted_average', // cost_calculation (weighted_average or last_cost)
                'Sample notes',
                true               // is_active
            ],
            [
                '',  // code - leave empty for auto-generation
                'second sample product',
                'Sample Product Description',
                'Finished Goods',  // item_type
                'Electronics',     // item_family
                'Computers',       // item_group
                'Laptops',         // item_category
                'Dell',            // item_brand
                'PCS',             // item_unit
                'Standard',        // item_profit_margin
                'SUP001',          // supplier code
                'VAT 11%',         // tax_code
                0.5,               // volume
                2.5,               // weight
                '1234567890123',   // barcode
                500.00,            // base_cost
                750.00,            // base_sell
                750.00,            // starting_price
                100,               // starting_quantity
                10,                // low_quantity_alert
                'last_cost',       // cost_calculation (weighted_average or last_cost)
                'Sample notes',
                true               // is_active
            ]
        ];

        if ($format === 'csv') {
            // Generate CSV file
            $callback = function() use ($headers, $sampleData) {
                $file = fopen('php://output', 'w');
                fputcsv($file, $headers);

                foreach ($sampleData as $row) {
                    fputcsv($file, $row);
                }

                fclose($file);
            };

            return response()->streamDownload($callback, 'item_import_template.csv', [
                'Content-Type' => 'text/csv',
            ]);
        } else {
            // Generate Excel file using Laravel Excel
            return Excel::download(
                new class($headers, $sampleData) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings {
                    protected $headers;
                    protected $data;

                    public function __construct($headers, $data)
                    {
                        $this->headers = $headers;
                        $this->data = $data;
                    }

                    public function array(): array
                    {
                        return $this->data;
                    }

                    public function headings(): array
                    {
                        return $this->headers;
                    }
                },
                'item_import_template.xlsx'
            );
        }
    }
}
