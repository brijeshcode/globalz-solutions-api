<?php

namespace App\Http\Controllers\Api\Customers;

use App\Helpers\ApiHelper;
use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customers\SaleOrdersStoreRequest;
use App\Http\Requests\Api\Customers\SaleOrdersUpdateRequest;
use App\Http\Resources\Api\Customers\SaleOrderResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customers\Customer;
use App\Models\Customers\Sale;
use App\Models\Items\Item;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaleOrdersController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = Sale::query()
            ->with([
                'customer:id,name,code',
                'currency:id,name,code,symbol,decimal_places,decimal_separator,thousand_separator',
                'warehouse:id,name',
                'salesperson:id,name',
                'approvedBy:id,name',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
            ->pending()
            ->searchable($request)
            ->sortable($request);

        // Role-based filtering: salesman can only see their own sale orders
        if (RoleHelper::isSalesman()) {
            $employee = RoleHelper::getSalesmanEmployee();
            if ($employee) {
                $query->where('salesperson_id', $employee->id);
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

        if ($request->has('salesperson_id')) {
            $query->bySalesperson($request->salesperson_id);
        }

        if ($request->has('prefix')) {
            $query->where('prefix', $request->prefix);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }
        $sales = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Sale orders retrieved successfully',
            $sales,
            SaleOrderResource::class
        );
    }

    public function store(SaleOrdersStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, &$sale) {
            // Extract items data
            $items = $data['items'];
            unset($data['items']);

            $totalProfit = 0;

            // Calculate total profit from sale items
            foreach ($items as $index => $itemData) {
                if (isset($itemData['item_id'])) {
                    $item = Item::with('itemPrice')->find($itemData['item_id']);
                    $items[$index]['item_code'] = $item?->code ?? $itemData['item_code'] ?? null;

                    // Get cost price from item's price
                    $costPrice = $item?->itemPrice?->price_usd ?? 0;
                    $sellingPrice = $itemData['price'] ?? 0;
                    $quantity = $itemData['quantity'] ?? 0;
                    $discountAmount = $itemData['unit_discount_amount'] ?? 0;

                    // Profit = (Sale Price - Discount) - Cost Price
                    $priceAfterDiscount = $sellingPrice - $discountAmount;
                    $unitProfit = $priceAfterDiscount - $costPrice;
                    $itemTotalProfit = $unitProfit * $quantity;

                    $items[$index]['cost_price'] = $costPrice;
                    $items[$index]['unit_profit'] = $unitProfit;
                    $items[$index]['total_profit'] = $itemTotalProfit;

                    $totalProfit += $itemTotalProfit;
                }
            }

            // Add total profit to sale data
            $data['total_profit'] = $totalProfit;

            // Create the sale order
            $sale = Sale::create($data);

            // Create sale items
            foreach ($items as $itemData) {
                $sale->items()->create($itemData);
            }
        });

        $sale->load([
            'customer:id,name,code',
            'currency:id,name,code,symbol,decimal_places,decimal_separator,thousand_separator',
            'warehouse:id,name',
            'salesperson:id,name',
            'items.item:id,short_name,code',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        $message = 'Sale order created successfully (pending approval)';

        return ApiResponse::store($message, new SaleOrderResource($sale));
    }

    public function show(Sale $sale): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Check if salesman can only view their own sale orders
        if (RoleHelper::isSalesman()) {
            $employee = RoleHelper::getSalesmanEmployee();
            if (is_null($employee) || $sale->salesperson_id != $employee->id) {
                return ApiResponse::customError('You can only view your own sale orders', 403);
            }
        }

        $sale->load([
            'customer:id,name,code,address,city,mobile,mof_tax_number',
            'currency:id,name,code,symbol,decimal_places,decimal_separator,thousand_separator',
            'warehouse:id,name,address_line_1',
            'salesperson:id,name',
            'approvedBy',
            'items.item:id,short_name,code,description,item_unit_id',
            'items.item.itemUnit',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::show(
            'Sale order retrieved successfully',
            new SaleOrderResource($sale)
        );
    }

    public function update(SaleOrdersUpdateRequest $request, Sale $sale): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Check if salesman can only update their own sale orders
        if (RoleHelper::isSalesman()) {
            $employee = RoleHelper::getSalesmanEmployee();
            if ($sale->salesperson_id !== $employee->id) {
                return ApiResponse::customError('You can only update your own sale orders', 403);
            }
        }

        if ($sale->isApproved()) {
            return ApiResponse::customError('Cannot update approved sales', 422);
        }

        $data = $request->validated();

        DB::transaction(function () use ($data, $sale) {
            if (isset($data['items'])) {
                $items = $data['items'];
                unset($data['items']);

                $totalProfit = 0;

                // Calculate total profit from sale items
                foreach ($items as $index => $itemData) {
                    if (isset($itemData['item_id'])) {
                        $item = Item::with('itemPrice')->find($itemData['item_id']);
                        $items[$index]['item_code'] = $item?->code ?? $itemData['item_code'] ?? null;

                        // Get cost price from item's price
                        $costPrice = $item?->itemPrice?->price_usd ?? 0;
                        $sellingPrice = $itemData['price'] ?? 0;
                        $quantity = $itemData['quantity'] ?? 0;
                        $discountAmount = $itemData['unit_discount_amount'] ?? 0;

                        // Profit = (Sale Price - Discount) - Cost Price
                        $priceAfterDiscount = $sellingPrice - $discountAmount;
                        $unitProfit = $priceAfterDiscount - $costPrice;
                        $itemTotalProfit = $unitProfit * $quantity;

                        $items[$index]['cost_price'] = $costPrice;
                        $items[$index]['unit_profit'] = $unitProfit;
                        $items[$index]['total_profit'] = $itemTotalProfit;

                        $totalProfit += $itemTotalProfit;
                    }
                }

                // Add total profit to sale data
                $data['total_profit'] = $totalProfit;

                // Get IDs of items in the request
                $requestItemIds = collect($items)->pluck('id')->filter()->toArray();

                // Delete items that are not in the request
                $sale->items()->whereNotIn('id', $requestItemIds)->delete();

                // Update or create items
                foreach ($items as $itemData) {
                    if (isset($itemData['id']) && $itemData['id']) {
                        // Update existing item
                        $sale->items()->where('id', $itemData['id'])->update($itemData);
                    } else {
                        // Create new item
                        $sale->items()->create($itemData);
                    }
                }
            }

            // Update the sale order
            $sale->update($data);
        });

        $sale->load([
            'customer:id,name,code',
            'currency:id,name,code,symbol,decimal_places,decimal_separator,thousand_separator',
            'warehouse:id,name',
            'salesperson:id,name',
            'items.item:id,short_name,description,code',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        $message = 'Sale order updated successfully (pending approval)';

        return ApiResponse::update($message, new SaleOrderResource($sale));
    }

    public function destroy(Sale $sale): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Check if salesman can only delete their own sale orders
        if (RoleHelper::isSalesman()) {
            $employee = RoleHelper::getSalesmanEmployee();
            if ($sale->salesperson_id !== $employee->id) {
                return ApiResponse::customError('You can only delete your own sale orders', 403);
            }
        }

        if ($sale->isApproved()) {
            return ApiResponse::customError('Cannot delete approved sales', 422);
        }

        DB::transaction(function () use ($sale) {
            // Soft delete all sale items first
            $sale->items()->delete();

            // Then soft delete the sale order
            $sale->delete();
        });

        return ApiResponse::delete('Sale order deleted successfully');
    }

    public function approve(Request $request, Sale $sale): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return ApiResponse::customError('You do not have permission to approve sales', 403);
        }

        if ($sale->isApproved()) {
            return ApiResponse::customError('Sale is already approved', 422);
        }

        $request->validate([
            'approve_note' => 'nullable|string|max:1000'
        ]);

        if(! $this->customerHasBalance($sale->customer_id, $sale->total_usd)){
            return ApiResponse::customError('Insufficient customer balance to fulfill this order.', 422);
        }

        $sale->update([
            'approved_by' => $user->id,
            'approved_at' => now(),
            'approve_note' => $request->approve_note
        ]);

        // Update customer balance when sale order is approved
        \App\Services\Customers\CustomerBalanceService::updateMonthlyTotal(
            $sale->customer_id,
            'sale',
            $sale->total_usd,
            $sale->id
        );

        $sale->load([
            'customer:id,name,code',
            'currency:id,name,code,symbol,decimal_places,decimal_separator,thousand_separator',
            'warehouse:id,name',
            'salesperson:id,name',
            'approvedBy:id,name',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::update(
            'Sale order approved successfully',
            new SaleOrderResource($sale)
        );
    }

    private function customerHasBalance(int $customerId, float $saleUsdTotal): bool
    {
        $customer = Customer::findOrFail($customerId);
        $balance = $customer->credit_limit + $customer->current_balance - $saleUsdTotal;

        return $balance > 0 ? true : false;
    }

    public function trashed(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $query = Sale::onlyTrashed()
            ->with([
                'customer:id,name,code',
                'currency:id,name,code,symbol,decimal_places,decimal_separator,thousand_separator',
                'warehouse:id,name',
                'salesperson:id,name',
                'approvedBy:id,name',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
            ->searchable($request)
            ->sortable($request);

        // Role-based filtering: salesman can only see their own trashed sale orders
        if (RoleHelper::isSalesman()) {
            $employee = RoleHelper::getSalesmanEmployee();
            if ($employee) {
                $query->where('salesperson_id', $employee->id);
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

        $sales = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed sale orders retrieved successfully',
            $sales,
            SaleOrderResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $sale = Sale::onlyTrashed()->findOrFail($id);

        // Check if salesman can only restore their own sale orders
        if (RoleHelper::isSalesman()) {
            $employee = RoleHelper::getSalesmanEmployee();
            if ($sale->salesperson_id !== $employee->id) {
                return ApiResponse::customError('You can only restore your own sale orders', 403);
            }
        }

        $sale->restore();
        $sale->items()->withTrashed()->restore();

        $sale->load([
            'customer:id,name,code',
            'currency:id,name,code,symbol,decimal_places,decimal_separator,thousand_separator',
            'warehouse:id,name',
            'salesperson:id,name',
            'approvedBy:id,name',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::update(
            'Sale order restored successfully',
            new SaleOrderResource($sale)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return ApiResponse::customError('Only admins can permanently delete sales', 403);
        }

        $sale = Sale::onlyTrashed()->findOrFail($id);

        DB::transaction(function () use ($sale) {
            // Force delete all sale items first (including soft deleted ones)
            $sale->items()->withTrashed()->forceDelete();

            // Then force delete the sale order
            $sale->forceDelete();
        });

        return ApiResponse::delete('Sale order permanently deleted successfully');
    }

    public function stats(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $query = Sale::query();

        // Role-based filtering for stats: salesman sees only their own stats
        if (RoleHelper::isSalesman()) {
            $employee = RoleHelper::getSalesmanEmployee();
            if ($employee) {
                $query->where('salesperson_id', $employee->id);
            }
        }

        $stats = [
            'total_sales' => (clone $query)->count(),
            'pending_sales' => (clone $query)->pending()->count(),
            'approved_sales' => (clone $query)->approved()->count(),
            'trashed_sales' => (clone $query)->onlyTrashed()->count(),
            'total_amount' => (clone $query)->approved()->sum('total'),
            'total_amount_usd' => (clone $query)->approved()->sum('total_usd'),
            'total_profit' => (clone $query)->approved()->sum('total_profit'),
            'sales_by_prefix' => (clone $query)->selectRaw('prefix, count(*) as count, sum(total) as total_amount')
                ->groupBy('prefix')
                ->get(),
            'sales_by_currency' => (clone $query)->with('currency:id,name,code')
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

        return ApiResponse::show('Sale order statistics retrieved successfully', $stats);
    }
}
