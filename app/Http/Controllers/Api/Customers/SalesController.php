<?php

namespace App\Http\Controllers\Api\Customers;

use App\Helpers\CurrencyHelper;
use App\Helpers\CustomersHelper;
use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customers\SalesStoreRequest;
use App\Http\Requests\Api\Customers\SalesUpdateRequest;
use App\Http\Resources\Api\Customers\SaleResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customers\Sale;
use App\Models\Customers\SaleItems;
use App\Models\Items\Item;
use App\Services\Inventory\InventoryService;
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

        // If no custom sort is specified, apply default ordering: Waiting first, then latest
        if (!$request->has('sort_by')) {
            $query
            ->orderByRaw("CASE WHEN status = 'Waiting' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN status = 'Shipped' THEN 0 ELSE 1 END")
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc');
        } else {
            $query->sortable($request);
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

        /** @var \App\Models\User $user */
        $user = \Illuminate\Support\Facades\Auth::user();

        // Auto-approve sales created by admin
        if (! $user->isAdmin()) {
            return ApiResponse::customError('only admin user can create sale directly.', 403);
        }

        $data['approved_by'] = $user->id;
        $data['approved_at'] = now();
        if (Sale::TAXFREEPREFIX == $data['prefix']) {
            $data['total_tax_amount'] = 0;
            $data['total_tax_amount_usd'] = 0;
            $data['invoice_tax_label'] = '';
        }

        $sale = DB::transaction(function () use ($data) {
            $saleItems = $data['items'];
            unset($data['items']);

            $totalProfit = 0;
            $subTotal = 0;
            $subTotalUsd = 0;
            $saleTotalTax = 0;
            $saleTotalTaxUsd = 0;
            $totalVolumeCbm = 0;
            $totalWeightKg = 0;

            // Calculate totals from sale items
            $currencyRate = $data['currency_rate'] ?? 1;
            $currencyId = $data['currency_id'];
            foreach ($saleItems as $index => $itemData) {
                if (isset($itemData['item_id'])) {
                    $item = Item::with('itemPrice')->find($itemData['item_id']);
                    $saleItems[$index]['item_code'] = $item?->code ?? $itemData['item_code'] ?? null;

                    // Get cost price from item's price (already in USD)
                    $costPrice = $item?->itemPrice?->price_usd ?? 0;
                    if (Sale::TAXFREEPREFIX == $data['prefix']) {

                        $saleItems[$index]['tax_percent'] = 0;
                        $saleItems[$index]['tax_amount'] = 0;
                        $saleItems[$index]['tax_amount_usd'] = 0;
                        $saleItems[$index]['tax_label'] = '';
                    }

                    // Base inputs from request
                    $sellingPrice = $itemData['price'] ?? 0;
                    $quantity = $itemData['quantity'] ?? 0;
                    $discountPercent = $itemData['discount_percent'] ?? 0;
                    $taxPercent = $itemData['tax_percent'] ?? 0;

                    // Convert base price to USD
                    $sellingPriceUsd = CurrencyHelper::toUsd($currencyId, $sellingPrice, $currencyRate);

                    // Step 1: Calculate unit discount amount from discount percent
                    $unitDiscountAmount = $sellingPrice * ($discountPercent / 100);
                    $unitDiscountAmountUsd = $sellingPriceUsd * ($discountPercent / 100);

                    // Step 2: Calculate total discount amount (unit_discount_amount * quantity)
                    $discountAmount = $unitDiscountAmount * $quantity;
                    $discountAmountUsd = $unitDiscountAmountUsd * $quantity;

                    // Step 3: Calculate net sell price (price after discount)
                    $netSellPrice = $sellingPrice - $unitDiscountAmount;
                    $netSellPriceUsd = $sellingPriceUsd - $unitDiscountAmountUsd;

                    // Step 4: Calculate tax amount based on net sell price (per unit)
                    $taxAmount = $taxPercent > 0 ? $netSellPrice * ($taxPercent / 100) : 0;
                    $taxAmountUsd = $taxPercent > 0 ? $netSellPriceUsd * ($taxPercent / 100) : 0;

                    // Step 5: Calculate TTC price (price including tax, per unit)
                    $ttcPrice = $netSellPrice + $taxAmount;
                    $ttcPriceUsd = $netSellPriceUsd + $taxAmountUsd;

                    // Step 6: Calculate total net sell prices
                    $totalNetSellPrice = $netSellPrice * $quantity;
                    $totalNetSellPriceUsd = $netSellPriceUsd * $quantity;

                    // Step 7: Calculate total tax amounts
                    $totalTaxAmount = $taxAmount * $quantity;
                    $totalTaxAmountUsd = $taxAmountUsd * $quantity;

                    // Step 8: Calculate total price
                    $totalPrice = $ttcPrice * $quantity;
                    $totalPriceUsd = $ttcPriceUsd * $quantity;

                    // Step 9: Calculate profit (excluding tax)
                    $unitProfit = $netSellPriceUsd - $costPrice;
                    $itemTotalProfit = $unitProfit * $quantity;

                    // Assign all calculated values to sale item
                    $saleItems[$index]['cost_price'] = $costPrice;
                    $saleItems[$index]['price_usd'] = $sellingPriceUsd;
                    $saleItems[$index]['discount_percent'] = $discountPercent;
                    $saleItems[$index]['unit_discount_amount'] = $unitDiscountAmount;
                    $saleItems[$index]['unit_discount_amount_usd'] = $unitDiscountAmountUsd;
                    $saleItems[$index]['discount_amount'] = $discountAmount;
                    $saleItems[$index]['discount_amount_usd'] = $discountAmountUsd;
                    $saleItems[$index]['net_sell_price'] = $netSellPrice;
                    $saleItems[$index]['net_sell_price_usd'] = $netSellPriceUsd;
                    $saleItems[$index]['tax_percent'] = $taxPercent;
                    $saleItems[$index]['tax_amount'] = $taxAmount;
                    $saleItems[$index]['tax_amount_usd'] = $taxAmountUsd;
                    $saleItems[$index]['ttc_price'] = $ttcPrice;
                    $saleItems[$index]['ttc_price_usd'] = $ttcPriceUsd;
                    $saleItems[$index]['total_net_sell_price'] = $totalNetSellPrice;
                    $saleItems[$index]['total_net_sell_price_usd'] = $totalNetSellPriceUsd;
                    $saleItems[$index]['total_tax_amount'] = $totalTaxAmount;
                    $saleItems[$index]['total_tax_amount_usd'] = $totalTaxAmountUsd;
                    $saleItems[$index]['total_price'] = $totalPrice;
                    $saleItems[$index]['total_price_usd'] = $totalPriceUsd;
                    $saleItems[$index]['unit_profit'] = $unitProfit;
                    $saleItems[$index]['total_profit'] = $itemTotalProfit;

                    // Aggregate totals for the sale
                    $totalProfit += $itemTotalProfit;
                    $subTotal += $totalNetSellPrice;
                    $subTotalUsd += $totalNetSellPriceUsd;
                    $saleTotalTax += $totalTaxAmount;  // Sum of all items' total_tax_amount
                    $saleTotalTaxUsd += $totalTaxAmountUsd;  // Sum of all items' total_tax_amount_usd
                    $totalVolumeCbm += $itemData['total_volume_cbm'] ?? 0;
                    $totalWeightKg += $itemData['total_weight_kg'] ?? 0;
                }
            }

            // Sale-level discount
            $additionalDiscount = $data['discount_amount'] ?? 0;
            $additionalDiscountUsd = $data['discount_amount_usd'] ?? 0;

            // Calculate sale totals
            $data['sub_total'] = $subTotal;
            $data['sub_total_usd'] = $subTotalUsd;
            $data['total_tax_amount'] = $saleTotalTax;
            $data['total_tax_amount_usd'] = $saleTotalTaxUsd;
            $data['total'] = $subTotal + $saleTotalTax - $additionalDiscount;
            $data['total_usd'] = $subTotalUsd + $saleTotalTaxUsd - $additionalDiscountUsd;
            $data['total_profit'] = $totalProfit - $additionalDiscountUsd;
            $data['total_volume_cbm'] = $totalVolumeCbm;
            $data['total_weight_kg'] = $totalWeightKg;

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
        // $this->updateAllSalePriceList();
        // Only show approved sales
        if (!$sale->isApproved()) {
            return ApiResponse::customError('Sale is not approved', 404);
        }

        $sale->load(['saleItems.item', 'saleItems.item.itemUnit:id,name', 'saleItems.item.taxCode:id,name,code,description,tax_percent', 'warehouse:id,name', 'currency', 'priceList:id,code,description', 'customer:id,name,code,address,city,mobile,mof_tax_number', 'salesperson:id,name']);

        return ApiResponse::show(
            'Sale retrieved successfully',
            new SaleResource($sale)
        );
    }
    
    private function updateAllSalePriceList(){
        $sales  = Sale::with('customer:id,price_list_id_INV,price_list_id_INX')->whereNull('price_list_id')->get();

        foreach($sales as $sale){
            $priceListId = $sale->prefix == Sale::TAXPREFIX ? $sale->customer->price_list_id_INV : $sale->customer->price_list_id_INX;
            $sale->price_list_id = $priceListId;
            $sale->save();
        }
    }

    public function update(SalesUpdateRequest $request, Sale $sale): JsonResponse
    {
        // Cannot update unapproved sales (they should be in sale orders)
        if (!RoleHelper::canAdmin()) {
            return ApiResponse::customError('Cannot update an approved sales', 422);
        }

        $data = $request->validated();
        $originalAmount = $sale->total_usd;

        DB::transaction(function () use ($data, $sale) {
            if (isset($data['items'])) {
                $saleItems = $data['items'];
                unset($data['items']);

                $totalProfit = 0;
                $subTotal = 0;
                $subTotalUsd = 0;
                $saleTotalTax = 0;
                $saleTotalTaxUsd = 0;
                $totalVolumeCbm = 0;
                $totalWeightKg = 0;

                // Calculate totals from sale items
                $currencyRate = $data['currency_rate'] ?? $sale->currency_rate ?? 1;

                foreach ($saleItems as $index => $itemData) {
                    if (isset($itemData['item_id'])) {
                        $item = Item::with('itemPrice')->find($itemData['item_id']);
                        $saleItems[$index]['item_code'] = $item?->code ?? $itemData['item_code'] ?? null;

                        // Get cost price from item's price (already in USD)
                        $costPrice = $item?->itemPrice?->price_usd ?? 0;

                        // Base inputs from request
                        $sellingPrice = $itemData['price'] ?? 0;
                        $quantity = $itemData['quantity'] ?? 0;
                        $discountPercent = $itemData['discount_percent'] ?? 0;
                        $taxPercent = $itemData['tax_percent'] ?? 0;

                        // Convert base price to USD
                        $sellingPriceUsd = CurrencyHelper::toUsd($sale->currency_id, $sellingPrice, $currencyRate);

                        // Step 1: Calculate unit discount amount from discount percent
                        $unitDiscountAmount = $sellingPrice * ($discountPercent / 100);
                        $unitDiscountAmountUsd = $sellingPriceUsd * ($discountPercent / 100);

                        // Step 2: Calculate total discount amount (unit_discount_amount * quantity)
                        $discountAmount = $unitDiscountAmount * $quantity;
                        $discountAmountUsd = $unitDiscountAmountUsd * $quantity;

                        // Step 3: Calculate net sell price (price after discount)
                        $netSellPrice = $sellingPrice - $unitDiscountAmount;
                        $netSellPriceUsd = $sellingPriceUsd - $unitDiscountAmountUsd;

                        // Step 4: Calculate tax amount based on net sell price (per unit)
                        $taxAmount = $taxPercent > 0 ? $netSellPrice * ($taxPercent / 100) : 0;
                        $taxAmountUsd = $taxPercent > 0 ? $netSellPriceUsd * ($taxPercent / 100) : 0;

                        // Step 5: Calculate TTC price (price including tax, per unit)
                        $ttcPrice = $netSellPrice + $taxAmount;
                        $ttcPriceUsd = $netSellPriceUsd + $taxAmountUsd;

                        // Step 6: Calculate total net sell prices
                        $totalNetSellPrice = $netSellPrice * $quantity;
                        $totalNetSellPriceUsd = $netSellPriceUsd * $quantity;

                        // Step 7: Calculate total tax amounts
                        $totalTaxAmount = $taxAmount * $quantity;
                        $totalTaxAmountUsd = $taxAmountUsd * $quantity;

                        // Step 8: Calculate total price
                        $totalPrice = $ttcPrice * $quantity;
                        $totalPriceUsd = $ttcPriceUsd * $quantity;

                        // Step 9: Calculate profit (excluding tax)
                        $unitProfit = $netSellPriceUsd - $costPrice;
                        $itemTotalProfit = $unitProfit * $quantity;

                        // Assign all calculated values to sale item
                        $saleItems[$index]['cost_price'] = $costPrice;
                        $saleItems[$index]['price_usd'] = $sellingPriceUsd;
                        $saleItems[$index]['discount_percent'] = $discountPercent;
                        $saleItems[$index]['unit_discount_amount'] = $unitDiscountAmount;
                        $saleItems[$index]['unit_discount_amount_usd'] = $unitDiscountAmountUsd;
                        $saleItems[$index]['discount_amount'] = $discountAmount;
                        $saleItems[$index]['discount_amount_usd'] = $discountAmountUsd;
                        $saleItems[$index]['net_sell_price'] = $netSellPrice;
                        $saleItems[$index]['net_sell_price_usd'] = $netSellPriceUsd;
                        $saleItems[$index]['tax_percent'] = $taxPercent;
                        $saleItems[$index]['tax_amount'] = $taxAmount;
                        $saleItems[$index]['tax_amount_usd'] = $taxAmountUsd;
                        $saleItems[$index]['ttc_price'] = $ttcPrice;
                        $saleItems[$index]['ttc_price_usd'] = $ttcPriceUsd;
                        $saleItems[$index]['total_net_sell_price'] = $totalNetSellPrice;
                        $saleItems[$index]['total_net_sell_price_usd'] = $totalNetSellPriceUsd;
                        $saleItems[$index]['total_tax_amount'] = $totalTaxAmount;
                        $saleItems[$index]['total_tax_amount_usd'] = $totalTaxAmountUsd;
                        $saleItems[$index]['total_price'] = $totalPrice;
                        $saleItems[$index]['total_price_usd'] = $totalPriceUsd;
                        $saleItems[$index]['unit_profit'] = $unitProfit;
                        $saleItems[$index]['total_profit'] = $itemTotalProfit;

                        // Aggregate totals for the sale
                        $totalProfit += $itemTotalProfit;
                        $subTotal += $totalNetSellPrice;
                        $subTotalUsd += $totalNetSellPriceUsd;
                        $saleTotalTax += $totalTaxAmount;
                        $saleTotalTaxUsd += $totalTaxAmountUsd;
                        $totalVolumeCbm += $itemData['total_volume_cbm'] ?? 0;
                        $totalWeightKg += $itemData['total_weight_kg'] ?? 0;
                    }
                }

                // Sale-level discount
                $additionalDiscount = $data['discount_amount'] ?? 0;
                $additionalDiscountUsd = $data['discount_amount_usd'] ?? 0;

                // Calculate sale totals
                $data['sub_total'] = $subTotal;
                $data['sub_total_usd'] = $subTotalUsd;
                $data['total_tax_amount'] = $saleTotalTax;
                $data['total_tax_amount_usd'] = $saleTotalTaxUsd;
                $data['total'] = $subTotal + $saleTotalTax - $additionalDiscount;
                $data['total_usd'] = $subTotalUsd + $saleTotalTaxUsd - $additionalDiscountUsd;
                $data['total_profit'] = $totalProfit - $additionalDiscountUsd;
                $data['total_volume_cbm'] = $totalVolumeCbm;
                $data['total_weight_kg'] = $totalWeightKg;

                // Get existing sale item IDs from the request
                $requestItemIds = collect($saleItems)
                    ->pluck('id')
                    ->filter()
                    ->values()
                    ->all();

                // Handle removed items - restore inventory explicitly before bulk delete
                $itemsToDelete = $sale->saleItems()->whereNotIn('id', $requestItemIds)->get();
                foreach ($itemsToDelete as $itemToDelete) {
                    InventoryService::add(
                        $itemToDelete->item_id,
                        $sale->warehouse_id,
                        $itemToDelete->quantity,
                        "Sale #{$sale->code} - Item removed"
                    );
                }
                // Bulk delete removed items (inventory already restored above)
                $sale->saleItems()->whereNotIn('id', $requestItemIds)->delete();

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
        if (!RoleHelper::canAdmin()) {
            return ApiResponse::customError('Cannot delete an approved sales', 422);
        }

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

    public function unapprove(Sale $sale): JsonResponse
    {
        // Only admin can unapprove sales
        if (!RoleHelper::canAdmin()) {
            return ApiResponse::customError('Only admin users can unapprove sales.', 403);
        }

        // Check if sale is approved
        if (!$sale->isApproved()) {
            return ApiResponse::customError('Sale is not approved.', 422);
        }

        // Check if sale is in Waiting status
        if ($sale->status !== Sale::STATUS_WAITING) {
            return ApiResponse::customError('Can only unapprove sales in Waiting status.', 422);
        }

        DB::transaction(function () use ($sale) {
            // Restore customer balance (add back the amount that was deducted)
            $customer = $sale->customer;
            if ($customer) {
                CustomersHelper::addBalance($customer, $sale->total_usd);
            }

            // Clear approval fields
            $sale->update([
                'approved_by' => null,
                'approved_at' => null,
                'approve_note' => null,
            ]);
        });

        $sale->load(['saleItems.item', 'warehouse', 'currency', 'customer', 'salesperson']);

        return ApiResponse::update(
            'Sale unapproved successfully',
            new SaleResource($sale)
        );
    }

    /**
     * Recalculate a specific sale by ID
     */
    public function recalculateSale(Sale $sale): JsonResponse
    {
        $this->recalculateAllSales();
        try {
            $sale->recalculateAllFields();
            $sale->load(['saleItems.item', 'warehouse', 'currency', 'customer', 'salesperson']);

            return ApiResponse::update(
                'Sale recalculated successfully',
                new SaleResource($sale)
            );
        } catch (\Exception $e) {
            return ApiResponse::customError(
                'Failed to recalculate sale: ' . $e->getMessage(),
                422
            );
        }
    }

    /**
     * Recalculate sales within a date range
     */
    public function recalculateSalesByDateRange(Request $request): JsonResponse
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        $query = Sale::query()
            ->whereBetween('date', [$request->from_date, $request->to_date]);

        // Optional: Filter by warehouse
        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        // Optional: Filter by customer
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        $sales = $query->get();
        $total = $sales->count();
        $success = 0;
        $failed = 0;
        $errors = [];

        foreach ($sales as $sale) {
            try {
                $sale->recalculateAllFields();
                $success++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "Sale #{$sale->id} ({$sale->prefix}-{$sale->code}): " . $e->getMessage();
            }
        }

        $result = [
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'date_range' => [
                'from_date' => $request->from_date,
                'to_date' => $request->to_date,
            ],
            'errors' => $errors,
        ];

        if ($failed > 0) {
            return ApiResponse::customError('Some sales failed to recalculate', 422, $result);
        }

        return ApiResponse::show('Sales recalculated successfully', $result);
    }

    /**
     * Recalculate ALL sales (use with caution)
     */
    public function recalculateAllSales(): JsonResponse
    {
        $total = Sale::count();
        $success = 0;
        $failed = 0;
        $errors = [];

        Sale::chunk(10, function ($sales) use (&$success, &$failed, &$errors) {
            foreach ($sales as $sale) {
                try {
                    $sale->recalculateAllFields();
                    $success++;
                } catch (\Exception $e) {
                    $failed++;
                    $errors[] = "Sale #{$sale->id}: " . $e->getMessage();
                }
            }
        });

        $result = [
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
        ];

        if ($failed > 0) {
            return ApiResponse::customError('Some sales failed to recalculate', 422, $result);
        }

        return ApiResponse::show('Sales recalculated successfully', $result);
    }

    private function saleQuery(Request $request)
    {
        $query = Sale::query()
            ->with(['saleItems.item', 'warehouse', 'currency', 'customer', 'salesperson'])
            ->approved()
            ->searchable($request)
            ;

        // Role-based filtering: salesman can only see their own returns
        if (RoleHelper::isSalesman() && !RoleHelper::isAdmin()) {
            $employee = RoleHelper::getSalesmanEmployee();
            if ($employee) {
                $query->where('salesperson_id', $employee->id);
            } else {
                // If employee not found, return no results
                $query->whereRaw('1 = 0');
            }
        }

        // Role-based filtering: warehouse manager can only see their assigned warehouses' sales
        if (RoleHelper::isWarehouseManager()  && !RoleHelper::isAdmin()) {
            $employee = RoleHelper::getWarehouseEmployee();
            if (!$employee) {
                // No employee found for warehouse manager, return empty query
                return $query->whereRaw('1 = 0');
            }

            $warehouseIds = $employee->warehouses()->pluck('warehouses.id');

            if ($warehouseIds->isEmpty()) {
                // No warehouses assigned to warehouse manager, return empty query
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
            $query->where('status', $request->status);
        }

        if ($request->has('date_from')) {
            $query->fromDate($request->date_from);
        }

        if ($request->has('date_to')) {
            $query->toDate($request->date_to);
        }
        return $query;
    }
}
