<?php

namespace App\Http\Controllers\Api\Customers;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customers\SalesStoreRequest;
use App\Http\Requests\Api\Customers\SalesUpdateRequest;
use App\Http\Resources\Api\Customers\SaleResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customers\Sale;
use App\Models\Customers\SaleItems;
use App\Models\Items\Item;
use App\Services\Customers\CustomerBalanceService;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = $this->saleQuery($request);

        $sales = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Sales retrieved successfully',
            $sales,
            SaleResource::class
        );
    }

    public function store(SalesStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        
        /** @var \App\Models\User $user */
        $user = \Illuminate\Support\Facades\Auth::user();

        // Auto-approve sales created by admin
        if (! $user->isAdmin()) {
            return ApiResponse::customError('only admin user can create sale directly.', 403);
        }

        $data['approved_by'] = $user->id;
        $data['approved_at'] = now();

        $sale = DB::transaction(function () use ($data) {
            $saleItems = $data['items'];
            unset($data['items']);

            $totalProfit = 0;

            // Calculate total profit from sale items
            foreach ($saleItems as $index => $itemData) {
                if (isset($itemData['item_id'])) {
                    $item = Item::with('itemPrice')->find($itemData['item_id']);
                    $saleItems[$index]['item_code'] = $item?->code ?? $itemData['item_code'] ?? null;

                    // Get cost price from item's price
                    $costPrice = $item?->itemPrice?->price_usd ?? 0;
                    $sellingPrice = $itemData['price'] ?? 0;
                    $quantity = $itemData['quantity'] ?? 0;
                    $discountAmount = $itemData['unit_discount_amount'] ?? 0;

                    // Profit = (Sale Price - Discount) - Cost Price
                    $priceAfterDiscount = $sellingPrice - $discountAmount;
                    $unitProfit = $priceAfterDiscount - $costPrice;
                    $itemTotalProfit = $unitProfit * $quantity;

                    $saleItems[$index]['cost_price'] = $costPrice;
                    $saleItems[$index]['unit_profit'] = $unitProfit;
                    $saleItems[$index]['total_profit'] = $itemTotalProfit;

                    $totalProfit += $itemTotalProfit;
                }
            }

            // Add total profit to sale data
            $data['total_profit'] = $totalProfit;

            $sale = Sale::create($data);

            foreach ($saleItems as $itemData) {
                $itemData['sale_id'] = $sale->id;
                SaleItems::create($itemData);
            }

            return $sale;
        });

        $sale->load(['saleItems.item', 'warehouse', 'currency']);

        return ApiResponse::store(
            'Sale created successfully',
            new SaleResource($sale)
        );
    }

    public function show(Sale $sale): JsonResponse
    {
        // Only show approved sales
        if (!$sale->isApproved()) {
            return ApiResponse::customError('Sale is not approved', 404);
        }

        $sale->load(['saleItems.item', 'saleItems.item.itemUnit:id,name', 'saleItems.item.taxCode:id,name,code,description,tax_percent', 'warehouse:id,name', 'currency', 'customer:id,name,code,address,city,mobile,mof_tax_number', 'salesperson:id,name']);

        return ApiResponse::show(
            'Sale retrieved successfully',
            new SaleResource($sale)
        );
    }

    public function update(SalesUpdateRequest $request, Sale $sale): JsonResponse
    {
        // Cannot update unapproved sales (they should be in sale orders)
        if(!RoleHelper::isAdmin()){
            return ApiResponse::customError('Cannot update an approved sales', 422);

        }

        $data = $request->validated();
        $originalAmount = $sale->total_usd;

        DB::transaction(function () use ($data, $sale) {
            if (isset($data['items'])) {
                $saleItems = $data['items'];
                unset($data['items']);

                $totalProfit = 0;

                // Calculate total profit from sale items
                foreach ($saleItems as $index => $itemData) {
                    if (isset($itemData['item_id'])) {
                        $item = \App\Models\Items\Item::with('itemPrice')->find($itemData['item_id']);
                        $saleItems[$index]['item_code'] = $item?->code ?? $itemData['item_code'] ?? null;

                        // Get cost price from item's price
                        $costPrice = $item?->itemPrice?->price_usd ?? 0;
                        $sellingPrice = $itemData['price'] ?? 0;
                        $quantity = $itemData['quantity'] ?? 0;
                        $discountAmount = $itemData['unit_discount_amount'] ?? 0;

                        // Profit = (Sale Price - Discount) - Cost Price
                        $priceAfterDiscount = $sellingPrice - $discountAmount;
                        $unitProfit = $priceAfterDiscount - $costPrice;
                        $itemTotalProfit = $unitProfit * $quantity;

                        $saleItems[$index]['cost_price'] = $costPrice;
                        $saleItems[$index]['unit_profit'] = $unitProfit;
                        $saleItems[$index]['total_profit'] = $itemTotalProfit;

                        $totalProfit += $itemTotalProfit;
                    }
                }

                // Add total profit to sale data
                $data['total_profit'] = $totalProfit;

                // Get existing sale item IDs from the request
                $requestItemIds = collect($saleItems)
                    ->pluck('id')
                    ->filter()
                    ->values()
                    ->all();

                // Delete sale items that are not in the request
                $sale->saleItems()
                    ->whereNotIn('id', $requestItemIds)
                    ->delete();

                foreach ($saleItems as $itemData) {
                    $itemData['sale_id'] = $sale->id;

                    if (isset($itemData['id']) && $itemData['id']) {
                        // Update existing sale item
                        $saleItem = SaleItems::find($itemData['id']);
                        if ($saleItem && $saleItem->sale_id === $sale->id) {
                            unset($itemData['id']); // Remove ID from update data
                            $saleItem->update($itemData);
                        }
                    } else {
                        // Create new sale item
                        unset($itemData['id']); // Remove null/empty ID
                        SaleItems::create($itemData);
                    }
                }
            }

            $sale->update($data);
        });

        // remove old amount 
        CustomerBalanceService::updateMonthlyTotal($sale->customer_id, 'sale', -$originalAmount, $sale->id);
        CustomerBalanceService::updateMonthlyTotal($sale->customer_id, 'sale', $sale->total_usd, $sale->id);

        $sale->load(['saleItems.item', 'warehouse', 'currency']);

        return ApiResponse::update(
            'Sale updated successfully',
            new SaleResource($sale)
        );
    }

    public function destroy(Sale $sale): JsonResponse
    {
        if(!RoleHelper::isAdmin()){
            return ApiResponse::customError('Cannot delete an approved sales', 422);
        }

        CustomerBalanceService::updateMonthlyTotal($sale->customer_id, 'sale', -$sale->total_usd, $sale->id);
        $sale->delete();

        return ApiResponse::delete('Sale deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = Sale::onlyTrashed()
            ->with(['saleItems.item', 'warehouse', 'currency'])
            ->approved()
            ->searchable($request)
            ->sortable($request);

        if ($request->has('warehouse_id')) {
            $query->byWarehouse($request->warehouse_id);
        }

        if ($request->has('currency_id')) {
            $query->byCurrency($request->currency_id);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        $sales = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed sales retrieved successfully',
            $sales,
            SaleResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $sale = Sale::onlyTrashed()->findOrFail($id);

        // Only restore approved sales
        if (!$sale->isApproved()) {
            return ApiResponse::customError('Can only restore approved sales', 422);
        }

        $sale->restore();
        $sale->saleItems()->withTrashed()->restore();

        $sale->load(['saleItems.item', 'warehouse', 'currency']);

        return ApiResponse::update(
            'Sale restored successfully',
            new SaleResource($sale)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $sale = Sale::onlyTrashed()->findOrFail($id);

        $sale->saleItems()->withTrashed()->forceDelete();
        $sale->forceDelete();

        return ApiResponse::delete('Sale permanently deleted successfully');
    }

    public function stats(Request $request): JsonResponse
    {
        $query = $this->saleQuery($request);

        $stats = [
            'total_sales' => (clone $query)->count(),
            'trashed_sales' => (clone $query)->onlyTrashed()->count(),
            'total_amount' => (clone $query)->sum('total'),
            'total_amount_usd' => (clone $query)->sum('total_usd'),
            'sales_by_status' => (clone $query)->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->status => $item->count];
                }),
            // 'sales_by_prefix' => (clone $query)->selectRaw('prefix, count(*) as count, sum(total) as total_amount')
            //     ->groupBy('prefix')
            //     ->get(),
            // 'sales_by_warehouse' => (clone $query)->with('warehouse:id,name')
            //     ->selectRaw('warehouse_id, count(*) as count, sum(total) as total_amount')
            //     ->groupBy('warehouse_id')
            //     ->having('count', '>', 0)
            //     ->get(),
            // 'sales_by_currency' => (clone $query)->with('currency:id,name,code')
            //     ->selectRaw('currency_id, count(*) as count, sum(total) as total_amount')
            //     ->groupBy('currency_id')
            //     ->having('count', '>', 0)
            //     ->get(),
        ];

        return ApiResponse::show('Sale statistics retrieved successfully', $stats);
    }

    public function changeStatus(Request $request, Sale $sale): JsonResponse
    {
        if (! $sale->isApproved()) {
            return ApiResponse::customError('Cannot change status off an un-approved sales order.', 422);
        }

        if (! RoleHelper::isWarehouseManager()) {
            return ApiResponse::customError('Only warehouse manager can change the status.', 422);
        }

        $sale->update(['status' => $request->status]);
        return ApiResponse::update(
            'Sale status updated successfully',
            new SaleResource($sale)
        );
        $sale->load(['saleItems.item', 'warehouse', 'currency']);
    }

    private function saleQuery(Request $request)
    {
        $query = Sale::query()
            ->with(['saleItems.item', 'warehouse', 'currency', 'customer', 'salesperson'])
            ->approved()
            ->searchable($request)
            ->sortable($request);

        // Role-based filtering: salesman can only see their own returns
        if (RoleHelper::isSalesman()) {
            $employee = RoleHelper::getSalesmanEmployee();
            $query->where('salesperson_id', $employee?->id);
        }

        // Role-based filtering: warehouse manager can only see their assigned warehouses' sales
        if (RoleHelper::isWarehouseManager()) {
            $employee = RoleHelper::getWarehouseEmployee();
            if ($employee) {
                $warehouseIds = $employee->warehouses()->pluck('warehouses.id');

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
            }
        } elseif ($request->has('warehouse_id')) {
            $query->byWarehouse($request->warehouse_id);
        }

        if ($request->has('warehouse_id')) {
            $query->byWarehouse($request->warehouse_id);
        }

        if ($request->has('currency_id')) {
            $query->byCurrency($request->currency_id);
        }

        if ($request->has('salesperson_id')) {
            $query->bySalesperson($request->salesperson_id);
        }

        if ($request->has('customer_id')) {
            $query->byCustomer($request->customer_id);
        }

        if ($request->has('status')) {
            $query->where('status' , $request->status);
        }

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->byDateRange($request->date_from, $request->date_to);
        }

        if ($request->has('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }
        return $query;
    }
}
