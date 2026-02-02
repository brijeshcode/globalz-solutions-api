<?php

namespace App\Http\Controllers\Api\Customers;

use App\Helpers\ApiHelper;
use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customers\CustomerReturnOrdersStoreRequest;
use App\Http\Requests\Api\Customers\CustomerReturnOrdersUpdateRequest;
use App\Http\Requests\Api\Customers\CustomerDirectReturnStoreRequest;
use App\Http\Requests\Api\Customers\CustomerDirectReturnUpdateRequest;
use App\Http\Resources\Api\Customers\CustomerReturnOrderResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customers\CustomerReturn;
use App\Models\Customers\SaleItems;
use App\Services\Customers\CustomerReturnService;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use function PHPUnit\Framework\isNull;

class CustomerReturnOrdersController extends Controller
{
    use HasPagination;

    protected $customerReturnService;

    public function __construct(CustomerReturnService $customerReturnService)
    {
        $this->customerReturnService = $customerReturnService;
    }

    public function index(Request $request): JsonResponse
    {

        $query =  $this->customerReturnQuery($request);
        $query->with([
                'customer:id,name,code,address,city,mobile,mof_tax_number',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'warehouse:id,name,address_line_1',
                'salesperson:id,name',
                'items',
                'approvedBy:id,name',
                'createdBy:id,name',
                'updatedBy:id,name'
        ]);
        $returns = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Customer returns retrieved successfully',
            $returns,
            CustomerReturnOrderResource::class
        );
    }

    public function store(CustomerReturnOrdersStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        $return = DB::transaction(function () use ($data, &$return) {
            // Extract items data
            $itemsInput = $data['items'];
            unset($data['items']);

            // Create the return order
            $return = CustomerReturn::create($data);

            // Prepare and create return items from sale items
            foreach ($itemsInput as $itemInput) {
                $itemData = $this->customerReturnService->prepareReturnItemData($itemInput, $data['prefix'],  $data['currency_rate']);
                $return->items()->create($itemData);
            }

            // Recalculate return totals
            $return->total = $return->items->sum('total_price');
            $return->total_usd = $return->items->sum('total_price_usd');
            $return->total_volume_cbm = $return->items->sum('total_volume_cbm');
            $return->total_weight_kg = $return->items->sum('total_weight_kg');
            $return->save();

            return $return;
        });

        $return->load([
            'customer:id,name,code,address,city,mobile,mof_tax_number',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'warehouse:id,name,address_line_1',
            'salesperson:id,name',
            'items.item:id,short_name,description,code',
            'items.saleItem',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        $message = 'Customer return order created successfully (pending approval)';

        return ApiResponse::store($message, new CustomerReturnOrderResource($return));
    }

    public function show(CustomerReturn $customerReturn): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Check if salesman can only view their own returns
        if (RoleHelper::isSalesman()) {
            $employee = RoleHelper::getSalesmanEmployee();
            if( is_null($employee) || $customerReturn->salesperson_id != $employee->id){
                return ApiResponse::customError('You can only view your own return orders', 403);
            }
        }

        $customerReturn->load([
            'customer:id,name,code,address,city,mobile,mof_tax_number',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'warehouse:id,name,address_line_1',
            'salesperson:id,name',
            'approvedBy:id,name',
            'items.item:id,short_name,code,description',
            'items.item.itemUnit:id,name,symbol',
            'items.item.taxCode:id,name,code,description,tax_percent',
            'items.sale:id,code,date,prefix',
            'items.saleItem:id,quantity',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::show(
            'Customer return retrieved successfully',
            new CustomerReturnOrderResource($customerReturn)
        );
    }

    public function update(CustomerReturnOrdersUpdateRequest $request, CustomerReturn $customerReturn): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Check if salesman can only update their own returns
        $employee = RoleHelper::getSalesmanEmployee();
        if ($user->isSalesman() && $customerReturn->salesperson_id !== $employee->id) {
            return ApiResponse::customError('You can only update your own return orders', 403);
        }

        if ($customerReturn->isApproved()) {
            return ApiResponse::customError('Cannot update approved returns', 422);
        }

        if ($customerReturn->isReceived()) {
            return ApiResponse::customError('Cannot update returns that have been received', 422);
        }

        $data = $request->validated();

        DB::transaction(function () use ($data, $customerReturn) {
            // Extract items data
            $itemsInput = $data['items'];
            $currencyRate = $data['currency_rate'] ?? $customerReturn->currency_rate;
            unset($data['items']);

            // Update the return order
            $customerReturn->update($data);

            // Get IDs of items in the request
            $requestItemIds = collect($itemsInput)->pluck('id')->filter()->toArray();

            // Delete items that are not in the request
            $customerReturn->items()->whereNotIn('id', $requestItemIds)->delete();

            // Update or create items
            foreach ($itemsInput as $itemInput) {
                $itemData = $this->customerReturnService->prepareReturnItemData($itemInput, $customerReturn->prefix, $currencyRate);

                if (isset($itemInput['id']) && $itemInput['id']) {
                    // Update existing item
                    $customerReturn->items()->where('id', $itemInput['id'])->update($itemData);
                } else {
                    // Create new item
                    $customerReturn->items()->create($itemData);
                }
            }

            // Recalculate return totals
            $customerReturn->refresh();
            $customerReturn->total = $customerReturn->items->sum('total_price');
            $customerReturn->total_usd = $customerReturn->items->sum('total_price_usd');
            $customerReturn->total_volume_cbm = $customerReturn->items->sum('total_volume_cbm');
            $customerReturn->total_weight_kg = $customerReturn->items->sum('total_weight_kg');
            $customerReturn->save();
        });

        $customerReturn->load([
            'customer:id,name,code,address,city,mobile,mof_tax_number',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'warehouse:id,name,address_line_1',
            'salesperson:id,name',
            'items.item:id,short_name,description,code',
            'items.saleItem',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        $message = 'Customer return order updated successfully (pending approval)';

        return ApiResponse::update($message, new CustomerReturnOrderResource($customerReturn));
    }

    /**
     * Store a direct customer return (not linked to any sale)
     */
    public function storeDirectReturn(CustomerDirectReturnStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        $return = DB::transaction(function () use ($data, &$return) {
            // Extract items data
            $itemsInput = $data['items'];
            unset($data['items']);

            // Create the return order
            $return = CustomerReturn::create($data);

            // Prepare and create return items directly from input
            foreach ($itemsInput as $itemInput) {
                $itemData = [
                    'item_id' => $itemInput['item_id'],
                    'item_code' => $itemInput['item_code'],
                    'quantity' => $itemInput['quantity'],
                    'price' => $itemInput['price'],
                    'price_usd' => $itemInput['price_usd'],
                    'discount_percent' => $itemInput['discount_percent'] ?? 0,
                    'unit_discount_amount' => $itemInput['unit_discount_amount'] ?? 0,
                    'unit_discount_amount_usd' => $itemInput['unit_discount_amount_usd'] ?? 0,
                    'tax_percent' => $itemInput['tax_percent'] ?? 0,
                    'tax_label' => $itemInput['tax_label'] ?? 'TVA',
                    'tax_amount' => $itemInput['tax_amount'] ?? 0,
                    'tax_amount_usd' => $itemInput['tax_amount_usd'] ?? 0,
                    'ttc_price' => $itemInput['ttc_price'] ?? 0,
                    'ttc_price_usd' => $itemInput['ttc_price_usd'] ?? 0,
                    'total_price' => $itemInput['total_price'],
                    'total_price_usd' => $itemInput['total_price_usd'],
                    'total_volume_cbm' => $itemInput['total_volume_cbm'] ?? 0,
                    'total_weight_kg' => $itemInput['total_weight_kg'] ?? 0,
                    'note' => $itemInput['note'] ?? null,
                    // No sale_id or sale_item_id for direct returns
                ];

                $return->items()->create($itemData);
            }

            // Recalculate return totals
            $return->total = $return->items->sum('total_price');
            $return->total_usd = $return->items->sum('total_price_usd');
            $return->total_volume_cbm = $return->items->sum('total_volume_cbm');
            $return->total_weight_kg = $return->items->sum('total_weight_kg');
            $return->save();

            return $return;
        });

        $return->load([
            'customer:id,name,code,address,city,mobile,mof_tax_number',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'warehouse:id,name,address_line_1',
            'salesperson:id,name',
            'items.item:id,short_name,description,code',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        $message = 'Direct customer return order created successfully (pending approval)';

        return ApiResponse::store($message, new CustomerReturnOrderResource($return));
    }

    /**
     * Update a direct customer return (not linked to any sale)
     */
    public function updateDirectReturn(CustomerDirectReturnUpdateRequest $request, CustomerReturn $customerReturn): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Check if salesman can only update their own returns
        $employee = RoleHelper::getSalesmanEmployee();
        if ($user->isSalesman() && $customerReturn->salesperson_id !== $employee->id) {
            return ApiResponse::customError('You can only update your own return orders', 403);
        }

        if ($customerReturn->isApproved()) {
            return ApiResponse::customError('Cannot update approved returns', 422);
        }

        if ($customerReturn->isReceived()) {
            return ApiResponse::customError('Cannot update returns that have been received', 422);
        }

        $data = $request->validated();

        DB::transaction(function () use ($data, $customerReturn) {
            // Extract items data
            $itemsInput = $data['items'];
            unset($data['items']);

            // Update the return order
            $customerReturn->update($data);

            // Get IDs of items in the request
            $requestItemIds = collect($itemsInput)->pluck('id')->filter()->toArray();

            // Delete items that are not in the request
            $customerReturn->items()->whereNotIn('id', $requestItemIds)->delete();

            // Update or create items
            foreach ($itemsInput as $itemInput) {
                $itemData = [
                    'item_id' => $itemInput['item_id'],
                    'item_code' => $itemInput['item_code'],
                    'quantity' => $itemInput['quantity'],
                    'price' => $itemInput['price'],
                    'price_usd' => $itemInput['price_usd'],
                    'discount_percent' => $itemInput['discount_percent'] ?? 0,
                    'unit_discount_amount' => $itemInput['unit_discount_amount'] ?? 0,
                    'unit_discount_amount_usd' => $itemInput['unit_discount_amount_usd'] ?? 0,
                    'tax_percent' => $itemInput['tax_percent'] ?? 0,
                    'tax_label' => $itemInput['tax_label'] ?? 'TVA',
                    'tax_amount' => $itemInput['tax_amount'] ?? 0,
                    'tax_amount_usd' => $itemInput['tax_amount_usd'] ?? 0,
                    'ttc_price' => $itemInput['ttc_price'] ?? 0,
                    'ttc_price_usd' => $itemInput['ttc_price_usd'] ?? 0,
                    'total_price' => $itemInput['total_price'],
                    'total_price_usd' => $itemInput['total_price_usd'],
                    'total_volume_cbm' => $itemInput['total_volume_cbm'] ?? 0,
                    'total_weight_kg' => $itemInput['total_weight_kg'] ?? 0,
                    'note' => $itemInput['note'] ?? null,
                    // No sale_id or sale_item_id for direct returns
                ];

                if (isset($itemInput['id']) && $itemInput['id']) {
                    // Update existing item
                    $customerReturn->items()->where('id', $itemInput['id'])->update($itemData);
                } else {
                    // Create new item
                    $customerReturn->items()->create($itemData);
                }
            }

            // Recalculate return totals
            $customerReturn->refresh();
            $customerReturn->total = $customerReturn->items->sum('total_price');
            $customerReturn->total_usd = $customerReturn->items->sum('total_price_usd');
            $customerReturn->total_volume_cbm = $customerReturn->items->sum('total_volume_cbm');
            $customerReturn->total_weight_kg = $customerReturn->items->sum('total_weight_kg');
            $customerReturn->save();
        });

        $customerReturn->load([
            'customer:id,name,code,address,city,mobile,mof_tax_number',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'warehouse:id,name,address_line_1',
            'salesperson:id,name',
            'items.item:id,short_name,description,code',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        $message = 'Direct customer return order updated successfully (pending approval)';

        return ApiResponse::update($message, new CustomerReturnOrderResource($customerReturn));
    }

    public function destroy(CustomerReturn $customerReturn): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Check if salesman can only delete their own returns
        if ($user->isSalesman() && $customerReturn->salesperson_id !== $user->id) {
            return ApiResponse::customError('You can only delete your own return orders', 403);
        }

        if ($customerReturn->isApproved()) {
            return ApiResponse::customError('Cannot delete approved returns', 422);
        }

        if ($customerReturn->isReceived()) {
            return ApiResponse::customError('Cannot delete returns that have been received', 422);
        }

        DB::transaction(function () use ($customerReturn) {
            // Soft delete all return items first
            $customerReturn->items()->delete();

            // Then soft delete the return order
            $customerReturn->delete();
        });

        return ApiResponse::delete('Customer return order deleted successfully');
    }

    public function approve(Request $request, CustomerReturn $customerReturn): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! RoleHelper::canAdmin() ) {
            return ApiResponse::customError('You do not have permission to approve returns', 403);
        }

        if ($customerReturn->isApproved()) {
            return ApiResponse::customError('Return is already approved', 422);
        }

        $request->validate([
            'approve_note' => 'nullable|string|max:1000'
        ]);

        $customerReturn->update([
            'approved_by' => $user->id,
            'approved_at' => now(),
            'approve_note' => $request->approve_note
        ]);
        // we do not update customer balance on approve, in case of return we update balance when it is received
        $customerReturn->load([
            'customer:id,name,code,address,city,mobile,mof_tax_number',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'warehouse:id,name,address_line_1',
            'salesperson:id,name',
            'approvedBy:id,name',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::update(
            'Customer return approved successfully',
            new CustomerReturnOrderResource($customerReturn)
        );
    }


    public function trashed(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $query = CustomerReturn::onlyTrashed()
            ->with([
                'customer:id,name,code,address,city,mobile,mof_tax_number',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'warehouse:id,name,address_line_1',
                'salesperson:id,name',
                'approvedBy:id,name',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
            ->searchable($request)
            ->sortable($request);

        // Role-based filtering: salesman can only see their own trashed returns
        if ($user->isSalesman()) {
            $query->where('salesperson_id', $user->id);
        }

        if ($request->has('customer_id')) {
            $query->byCustomer($request->customer_id);
        }

        if ($request->has('currency_id')) {
            $query->byCurrency($request->currency_id);
        }

        $returns = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed customer returns retrieved successfully',
            $returns,
            CustomerReturnOrderResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $return = CustomerReturn::onlyTrashed()->findOrFail($id);

        // Check if salesman can only restore their own returns
        if ($user->isSalesman() && $return->salesperson_id !== $user->id) {
            return ApiResponse::customError('You can only restore your own return orders', 403);
        }

        $return->restore();

        $return->load([
            'customer:id,name,code,address,city,mobile,mof_tax_number',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'warehouse:id,name,address_line_1',
            'salesperson:id,name',
            'approvedBy:id,name',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::update(
            'Customer return restored successfully',
            new CustomerReturnOrderResource($return)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return ApiResponse::customError('Only admins can permanently delete returns', 403);
        }

        $return = CustomerReturn::onlyTrashed()->findOrFail($id);

        DB::transaction(function () use ($return) {
            // Force delete all return items first (including soft deleted ones)
            $return->items()->withTrashed()->forceDelete();

            // Then force delete the return order
            $return->forceDelete();
        });

        return ApiResponse::delete('Customer return permanently deleted successfully');
    }

    public function stats(Request $request): JsonResponse
    {

        $query = $this->customerReturnQuery($request);

        $stats = [
            'total_returns' => (clone $query)->count(),
            // 'pending_returns' => (clone $query)->pending()->count(),
            // 'approved_returns' => (clone $query)->approved()->count(),
            // 'received_returns' => (clone $query)->received()->count(),
            // 'trashed_returns' => (clone $query)->onlyTrashed()->count(),
            'total_amount' => (clone $query)->sum('total'),
            'total_amount_usd' => (clone $query)->sum('total_usd'),
            // 'returns_by_prefix' => (clone $query)->selectRaw('prefix, count(*) as count, sum(total) as total_amount')
            //     ->groupBy('prefix')
            //     ->get(),
            // 'returns_by_currency' => (clone $query)->with('currency:id,name,code')
            //     ->selectRaw('currency_id, count(*) as count, sum(total) as total_amount')
            //     ->groupBy('currency_id')
            //     ->having('count', '>', 0)
            //     ->get(),
            // 'recent_approved' => (clone $query)->approved()
            //     ->with(['customer:id,name,code', 'approvedBy:id,name'])
            //     ->orderBy('approved_at', 'desc')
            //     ->limit(5)
            //     ->get(),
        ];

        return ApiResponse::show('Customer return statistics retrieved successfully', $stats);
    }

    /**
     * Get sale items available for return grouped by item with return history
     */
    public function getSaleItemsForReturn(Request $request): JsonResponse
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
        ]);

        $customerId = $request->customer_id;

        // Fetch all sale items for this customer's approved sales
        $saleItems = SaleItems::whereHas('sale', function ($query) use ($customerId) {
                $query->where('customer_id', $customerId)
                    ->whereNotNull('approved_by') // Only approved sales
                    ->whereNull('deleted_at');
            })
            ->with([
                'sale:id,code,date,customer_id,currency_id,warehouse_id,salesperson_id,prefix',
                'sale.currency:id,name,code,symbol,symbol_position',
                'sale.warehouse:id,name',
                'sale.salesperson:id,name',
                'item:id,code,description,short_name,item_unit_id',
                'item.itemUnit:id,name,short_name'
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        // Group sale items by item_id
        $groupedByItem = $saleItems->groupBy('item_id')->map(function ($itemSales, $itemId) {
            $firstItem = $itemSales->first();

            // Process each sale transaction for this item
            $salesTransactions = $itemSales->map(function ($saleItem) {
                // Calculate returned quantity for this specific sale item
                $returnedQuantity = \App\Models\Customers\CustomerReturnItem::where('sale_item_id', $saleItem->id)
                    ->whereHas('customerReturn', function ($query) {
                        $query->whereNull('deleted_at');
                    })
                    ->sum('quantity');

                $availableQuantity = $saleItem->quantity - $returnedQuantity;

                return [
                    // Sale transaction information
                    'sale_id' => $saleItem->sale_id,
                    'sale_code' => $saleItem->sale->code ?? null,
                    'sale_prefix' => $saleItem->sale->prefix ?? null,
                    'sale_date' => $saleItem->sale->date ?? null,
                    'warehouse' => $saleItem->sale->warehouse ?? null,
                    'salesperson' => $saleItem->sale->salesperson ?? null,
                    'currency' => $saleItem->sale->currency ?? null,

                    // Sale item reference
                    'sale_item_id' => $saleItem->id,

                    // Quantity information for this transaction
                    'quantity' => (float) $saleItem->quantity,
                    'returned_quantity' => (float) $returnedQuantity,
                    'available_quantity' => (float) $availableQuantity,
                    'can_return' => $availableQuantity > 0,

                    // Pricing information (per unit)
                    'price' => (float) $saleItem->price,
                    'price_usd' => (float) $saleItem->price_usd,

                    // Discount information
                    'discount_percent' => (float) $saleItem->discount_percent,
                    'unit_discount_amount' => (float) $saleItem->unit_discount_amount,
                    'unit_discount_amount_usd' => (float) $saleItem->unit_discount_amount_usd,

                    // Tax information
                    'tax_percent' => (float) $saleItem->tax_percent,
                    'tax_label' => $saleItem->tax_label,
                    'tax_amount' => (float) $saleItem->tax_amount,
                    'tax_amount_usd' => (float) $saleItem->tax_amount_usd,

                    // TTC price (per unit)
                    'ttc_price' => (float) $saleItem->ttc_price,
                    'ttc_price_usd' => (float) $saleItem->ttc_price_usd,

                    // Total prices (for this transaction)
                    'total_price' => (float) $saleItem->total_price,
                    'total_price_usd' => (float) $saleItem->total_price_usd,

                    'unit_volume_cbm' => $saleItem->unit_volume_cbm,
                    'unit_weight_kg' => $saleItem->unit_weight_kg,
                    // Note
                    'note' => $saleItem->note,
                ];
            });

            // Calculate totals for this item across all sales
            $totalSold = $salesTransactions->sum('quantity');
            $totalReturned = $salesTransactions->sum('returned_quantity');
            $totalAvailable = $salesTransactions->sum('available_quantity');

            return [
                // Item information
                'item_id' => $itemId,
                'item_code' => $firstItem->item_code,
                'item' => $firstItem->item,

                // Aggregate quantities across all sales
                'total_sold' => (float) $totalSold,
                'total_returned' => (float) $totalReturned,
                'total_available' => (float) $totalAvailable,
                'can_return' => $totalAvailable > 0,

                // All sale transactions for this item
                'sales' => $salesTransactions->values(),
            ];
        });

        // Filter to only show items that can be returned
        $returnableItems = $groupedByItem->filter(function ($item) {
            return $item['can_return'];
        })->values();

        return ApiResponse::show(
            'Sale items grouped by product with return information retrieved successfully',
            [
                'customer_id' => $customerId,
                'total_products' => $groupedByItem->count(),
                'returnable_products' => $returnableItems->count(),
                'items' => $returnableItems
            ]
        );
    }

    public function customerReturnQuery(Request $request)
    {
        $query = CustomerReturn::query()
            ->pending()
            ->searchable($request)
            ->sortable($request);

        // Role-based filtering: salesman can only see their own returns
        if (RoleHelper::isSalesman()) {
            $employee = RoleHelper::getSalesmanEmployee();
            if ($employee) {
                $query->where('salesperson_id', $employee->id);
            } else {
                // If employee not found, return no results
                $query->whereRaw('1 = 0');
            }
        }

        if ($request->has('customer_id')) {
            $query->byCustomer($request->customer_id);
        }

        if ($request->has('currency_id')) {
            $query->byCurrency($request->currency_id);
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

        if ($request->has('prefix')) {
            $query->byPrefix($request->prefix);
        }

        if ($request->has('start_date')) {
            $query->fromDate($request->start_date);
        }

        if ($request->has('end_date')) {
            $query->toDate($request->end_date);
        }

        return $query;
    }
}
