<?php

namespace App\Http\Controllers\Api\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customers\SalesStoreRequest;
use App\Http\Requests\Api\Customers\SalesUpdateRequest;
use App\Http\Resources\Api\Customers\SaleResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customers\Sale;
use App\Models\Customers\SaleItems;
use App\Models\Items\Item;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = Sale::query()
            ->with(['saleItems.item', 'warehouse', 'currency', 'customer', 'salesperson'])
            ->searchable($request)
            ->sortable($request);

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

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

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

                    $unitProfit = $sellingPrice - $costPrice;
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
        $sale->load(['saleItems.item', 'saleItems.item.itemUnit:id,name', 'saleItems.item.taxCode:id,name,code,description,tax_percent', 'warehouse:id,name', 'currency', 'customer:id,name,code,address,city,mobile,mof_tax_number', 'salesperson:id,name']);

        return ApiResponse::show(
            'Sale retrieved successfully',
            new SaleResource($sale)
        );
    }

    public function update(SalesUpdateRequest $request, Sale $sale): JsonResponse
    {
        $data = $request->validated();

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

                        $unitProfit = $sellingPrice - $costPrice;
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

        $sale->load(['saleItems.item', 'warehouse', 'currency']);

        return ApiResponse::update(
            'Sale updated successfully',
            new SaleResource($sale)
        );
    }

    public function destroy(Sale $sale): JsonResponse
    {
        $sale->delete();

        return ApiResponse::delete('Sale deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = Sale::onlyTrashed()
            ->with(['saleItems.item', 'warehouse', 'currency'])
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

    public function stats(): JsonResponse
    {
        $stats = [
            'total_sales' => Sale::count(),
            'trashed_sales' => Sale::onlyTrashed()->count(),
            'total_amount' => Sale::sum('total'),
            'total_amount_usd' => Sale::sum('total_usd'),
            'sales_by_prefix' => Sale::selectRaw('prefix, count(*) as count, sum(total) as total_amount')
                ->groupBy('prefix')
                ->get(),
            'sales_by_warehouse' => Sale::with('warehouse:id,name')
                ->selectRaw('warehouse_id, count(*) as count, sum(total) as total_amount')
                ->groupBy('warehouse_id')
                ->having('count', '>', 0)
                ->get(),
            'sales_by_currency' => Sale::with('currency:id,name,code')
                ->selectRaw('currency_id, count(*) as count, sum(total) as total_amount')
                ->groupBy('currency_id')
                ->having('count', '>', 0)
                ->get(),
        ];

        return ApiResponse::show('Sale statistics retrieved successfully', $stats);
    }
}
