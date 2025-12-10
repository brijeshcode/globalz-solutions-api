<?php

namespace App\Http\Controllers\Api\Customers;

use App\Helpers\ApiHelper;
use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customers\CustomerReturnOrdersStoreRequest;
use App\Http\Requests\Api\Customers\CustomerReturnOrdersUpdateRequest;
use App\Http\Resources\Api\Customers\CustomerReturnOrderResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customers\CustomerReturn;
use App\Models\Customers\SaleItems;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use function PHPUnit\Framework\isNull;

class CustomerReturnOrdersController extends Controller
{
    use HasPagination;

    /**
     * Prepare return item data from sale item
     */
    private function prepareReturnItemData(array $itemInput, float $currencyRate): array
    {
        $saleItem =  SaleItems::with(['sale', 'item'])->findOrFail($itemInput['sale_item_id']);
        $returnQuantity = $itemInput['quantity'];

        // Copy all data from sale item and recalculate based on return quantity
        return [
            'item_code' => $saleItem->item_code,
            'item_id' => $saleItem->item_id,
            'sale_id' => $saleItem->sale_id,
            'sale_item_id' => $saleItem->id,
            'quantity' => $returnQuantity,

            // Prices (per unit from sale)
            'price' => $saleItem->price,
            'price_usd' => $saleItem->price_usd,

            // Tax details
            'tax_percent' => $saleItem->tax_percent,
            'tax_label' => $saleItem->tax_label ?? 'TVA',
            'tax_amount' => $saleItem->tax_amount,
            'tax_amount_usd' => $saleItem->tax_amount_usd,

            // TTC price (per unit)
            'ttc_price' => $saleItem->ttc_price,
            'ttc_price_usd' => $saleItem->ttc_price_usd,

            // Discount details
            'discount_percent' => $saleItem->discount_percent,
            'unit_discount_amount' => $saleItem->unit_discount_amount,
            'unit_discount_amount_usd' => $saleItem->unit_discount_amount_usd,

            // Calculate total discount amount for return quantity
            'discount_amount' => $saleItem->unit_discount_amount * $returnQuantity,
            'discount_amount_usd' => $saleItem->unit_discount_amount_usd * $returnQuantity,

            // Calculate total prices for return quantity
            // 'total_price' => $saleItem->price * $returnQuantity - ($saleItem->unit_discount_amount * $returnQuantity),
            // 'total_price_usd' => $saleItem->price_usd * $returnQuantity - ($saleItem->unit_discount_amount_usd * $returnQuantity),

            'total_price' => $saleItem->ttc_price * $returnQuantity,
            'total_price_usd' => $saleItem->ttc_price_usd * $returnQuantity,

            // Calculate return profit (negative because it's a return)
            // total_price_usd - (cost_price * quantity) - we use the cost from sale item
            // 'total_profit' => ($saleItem->price_usd * $returnQuantity - ($saleItem->unit_discount_amount_usd * $returnQuantity)) - ($saleItem->cost_price * $returnQuantity),
            'total_profit' => $saleItem->unit_profit * $returnQuantity,

            // Volume and weight
            'total_volume_cbm' => ($saleItem->item->volume_cbm ?? 0) * $returnQuantity,
            'total_weight_kg' => ($saleItem->item->weight_kg ?? 0) * $returnQuantity,

            // Note
            'note' => $itemInput['note'] ?? null,
        ];
    }

    public function index(Request $request): JsonResponse
    {

        $query = CustomerReturn::query()
            ->with([
                'customer:id,name,code',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'warehouse:id,name',
                'salesperson:id,name',
                'approvedBy:id,name',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
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

        if ($request->has('warehouse_id')) {
            $query->byWarehouse($request->warehouse_id);
        }

        if ($request->has('prefix')) {
            $query->byPrefix($request->prefix);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

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

        DB::transaction(function () use ($data, &$return) {
            // Extract items data
            $itemsInput = $data['items'];
            unset($data['items']);

            // Create the return order
            $return = CustomerReturn::create($data);

            // Prepare and create return items from sale items
            foreach ($itemsInput as $itemInput) {
                $itemData = $this->prepareReturnItemData($itemInput, $data['currency_rate']);
                $return->items()->create($itemData);
            }

            // Recalculate return totals
            $return->total = $return->items->sum('total_price');
            $return->total_usd = $return->items->sum('total_price_usd');
            $return->total_volume_cbm = $return->items->sum('total_volume_cbm');
            $return->total_weight_kg = $return->items->sum('total_weight_kg');
            $return->save();
        });

        $return->load([
            'customer:id,name,code',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'warehouse:id,name',
            'salesperson:id,name',
            'items.item:id,short_name,code',
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
            'customer:id,name,code,address,city,mobile',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'warehouse:id,name,address_line_1',
            'salesperson:id,name',
            'approvedBy',
            'items.item:id,short_name,code,item_unit_id',
            'items.item.itemUnit',
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
                $itemData = $this->prepareReturnItemData($itemInput, $currencyRate);

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
            'customer:id,name,code',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'warehouse:id,name',
            'salesperson:id,name',
            'items.item:id,short_name,description,code',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        $message = 'Customer return order updated successfully (pending approval)';

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

        if (!$user->isAdmin() ) {
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
            'customer:id,name,code',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'warehouse:id,name',
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
                'customer:id,name,code',
                'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
                'warehouse:id,name',
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
            'customer:id,name,code',
            'currency:id,name,code,symbol,symbol_position,decimal_places,decimal_separator,thousand_separator,calculation_type',
            'warehouse:id,name',
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

    public function stats(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $query = CustomerReturn::query();

        // Role-based filtering for stats: salesman sees only their own stats
        if ($user->isSalesman()) {
            $query->where('salesperson_id', $user->id);
        }

        $stats = [
            'total_returns' => (clone $query)->count(),
            'pending_returns' => (clone $query)->pending()->count(),
            'approved_returns' => (clone $query)->approved()->count(),
            'received_returns' => (clone $query)->received()->count(),
            'trashed_returns' => (clone $query)->onlyTrashed()->count(),
            'total_amount' => (clone $query)->approved()->sum('total'),
            'total_amount_usd' => (clone $query)->approved()->sum('total_usd'),
            'returns_by_prefix' => (clone $query)->selectRaw('prefix, count(*) as count, sum(total) as total_amount')
                ->groupBy('prefix')
                ->get(),
            'returns_by_currency' => (clone $query)->with('currency:id,name,code')
                ->selectRaw('currency_id, count(*) as count, sum(total) as total_amount')
                ->groupBy('currency_id')
                ->having('count', '>', 0)
                ->get(),
            'recent_approved' => (clone $query)->approved()
                ->with(['customer:id,name,code', 'approvedBy:id,name'])
                ->orderBy('approved_at', 'desc')
                ->limit(5)
                ->get(),
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
                'item:id,code,short_name,item_unit_id',
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
}
