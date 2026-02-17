<?php

namespace App\Http\Controllers\Api\Customers;

use App\Helpers\ApiHelper;
use App\Helpers\CurrencyHelper;
use App\Helpers\CustomersHelper;
use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customers\SaleOrdersStoreRequest;
use App\Http\Requests\Api\Customers\SaleOrdersUpdateRequest;
use App\Http\Resources\Api\Customers\SaleOrderResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customers\Customer;
use App\Models\Customers\Sale;
use App\Models\Items\Item;
use App\Services\Inventory\InventoryService;
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
                'customer:id,name,code,city',
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

        if ($request->has('salesperson_id')) {
            $query->bySalesperson($request->salesperson_id);
        }

        if ($request->has('prefix')) {
            $query->where('prefix', $request->prefix);
        }

        if ($request->has('start_date')) {
            $query->fromDate($request->start_date);
        }

        if ($request->has('end_date')) {
            $query->toDate($request->end_date);
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

        if(Sale::TAXFREEPREFIX == $data['prefix']){
            $data['total_tax_amount'] = 0;
            $data['total_tax_amount_usd'] = 0;
            $data['invoice_tax_label'] = '';
        }

        DB::transaction(function () use ($data, &$sale) {
            // Extract items data
            $items = $data['items'];
            unset($data['items']);

            $totalProfit = 0;
            $subTotal = 0;
            $subTotalUsd = 0;
            $saleTotalTax = 0;
            $saleTotalTaxUsd = 0;
            $totalVolumeCbm = 0;
            $totalWeightKg = 0;

            $currencyRate = $data['currency_rate'] ?? 1;
            $currencyId = $data['currency_id'];

            // Calculate total profit from sale items
            foreach ($items as $index => $itemData) {
                if (isset($itemData['item_id'])) {
                    $item = Item::with('itemPrice')->find($itemData['item_id']);
                    $items[$index]['item_code'] = $item?->code ?? $itemData['item_code'] ?? null;

                    // Get cost price from item's price (cost price always in usd)
                    $costPrice = $item?->itemPrice?->price_usd ?? 0;

                    if(Sale::TAXFREEPREFIX == $data['prefix']){
                        $items[$index]['tax_percent'] = 0;
                        $items[$index]['tax_amount'] = 0;
                        $items[$index]['tax_amount_usd'] = 0;
                        $items[$index]['tax_label'] = '';
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
                    $items[$index]['cost_price'] = $costPrice;
                    $items[$index]['price_usd'] = $sellingPriceUsd;
                    $items[$index]['discount_percent'] = $discountPercent;
                    $items[$index]['unit_discount_amount'] = $unitDiscountAmount;
                    $items[$index]['unit_discount_amount_usd'] = $unitDiscountAmountUsd;
                    $items[$index]['discount_amount'] = $discountAmount;
                    $items[$index]['discount_amount_usd'] = $discountAmountUsd;
                    $items[$index]['net_sell_price'] = $netSellPrice;
                    $items[$index]['net_sell_price_usd'] = $netSellPriceUsd;
                    $items[$index]['tax_percent'] = $taxPercent;
                    $items[$index]['tax_amount'] = $taxAmount;
                    $items[$index]['tax_amount_usd'] = $taxAmountUsd;
                    $items[$index]['ttc_price'] = $ttcPrice;
                    $items[$index]['ttc_price_usd'] = $ttcPriceUsd;
                    $items[$index]['total_net_sell_price'] = $totalNetSellPrice;
                    $items[$index]['total_net_sell_price_usd'] = $totalNetSellPriceUsd;
                    $items[$index]['total_tax_amount'] = $totalTaxAmount;
                    $items[$index]['total_tax_amount_usd'] = $totalTaxAmountUsd;
                    $items[$index]['total_price'] = $totalPrice;
                    $items[$index]['total_price_usd'] = $totalPriceUsd;
                    $items[$index]['unit_profit'] = $unitProfit;
                    $items[$index]['total_profit'] = $itemTotalProfit;

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
            'customer:id,name,code,address,city,mobile,mof_tax_number,price_list_id_INV,price_list_id_INX',
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
                $subTotal = 0;
                $subTotalUsd = 0;
                $saleTotalTax = 0;
                $saleTotalTaxUsd = 0;
                $totalVolumeCbm = 0;
                $totalWeightKg = 0;

                $currencyRate = $data['currency_rate'] ?? $sale->currency_rate ?? 1;

                // Calculate total profit from sale items
                foreach ($items as $index => $itemData) {
                    if (isset($itemData['item_id'])) {
                        $item = Item::with('itemPrice')->find($itemData['item_id']);
                        $items[$index]['item_code'] = $item?->code ?? $itemData['item_code'] ?? null;

                        // Get cost price from item's price (already in USD)
                        $costPrice = $item?->itemPrice?->price_usd ?? 0;

                        // Base inputs from request
                        $sellingPrice = $itemData['price'] ?? 0;
                        $quantity = $itemData['quantity'] ?? 0;
                        $discountPercent = $itemData['discount_percent'] ?? 0;
                        $prefix = $data['prefix'] ?? $sale->prefix;
                        $taxPercent = ($prefix == Sale::TAXFREEPREFIX) ? 0 : ($itemData['tax_percent'] ?? 0);

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

                        // For tax-free prefix, clear tax label
                        if ($prefix == Sale::TAXFREEPREFIX) {
                            $items[$index]['tax_label'] = '';
                        }

                        // Assign all calculated values to sale item
                        $items[$index]['cost_price'] = $costPrice;
                        $items[$index]['price_usd'] = $sellingPriceUsd;
                        $items[$index]['discount_percent'] = $discountPercent;
                        $items[$index]['unit_discount_amount'] = $unitDiscountAmount;
                        $items[$index]['unit_discount_amount_usd'] = $unitDiscountAmountUsd;
                        $items[$index]['discount_amount'] = $discountAmount;
                        $items[$index]['discount_amount_usd'] = $discountAmountUsd;
                        $items[$index]['net_sell_price'] = $netSellPrice;
                        $items[$index]['net_sell_price_usd'] = $netSellPriceUsd;
                        $items[$index]['tax_percent'] = $taxPercent;
                        $items[$index]['tax_amount'] = $taxAmount;
                        $items[$index]['tax_amount_usd'] = $taxAmountUsd;
                        $items[$index]['ttc_price'] = $ttcPrice;
                        $items[$index]['ttc_price_usd'] = $ttcPriceUsd;
                        $items[$index]['total_net_sell_price'] = $totalNetSellPrice;
                        $items[$index]['total_net_sell_price_usd'] = $totalNetSellPriceUsd;
                        $items[$index]['total_tax_amount'] = $totalTaxAmount;
                        $items[$index]['total_tax_amount_usd'] = $totalTaxAmountUsd;
                        $items[$index]['total_price'] = $totalPrice;
                        $items[$index]['total_price_usd'] = $totalPriceUsd;
                        $items[$index]['unit_profit'] = $unitProfit;
                        $items[$index]['total_profit'] = $itemTotalProfit;

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

                // Handle prefix-dependent fields
                $prefix = $data['prefix'] ?? $sale->prefix;
                if ($prefix == Sale::TAXFREEPREFIX) {
                    $saleTotalTax = 0;
                    $saleTotalTaxUsd = 0;
                    $data['total_tax_amount'] = 0;
                    $data['total_tax_amount_usd'] = 0;
                    $data['invoice_tax_label'] = '';
                } else {
                    $data['total_tax_amount'] = $saleTotalTax;
                    $data['total_tax_amount_usd'] = $saleTotalTaxUsd;
                    $data['invoice_tax_label'] = 'TVA 11%';
                }

                // Update price_list_id when prefix changes
                $customer = Customer::select('price_list_id_INV', 'price_list_id_INX')->find($data['customer_id'] ?? $sale->customer_id);
                if ($customer) {
                    $data['price_list_id'] = $prefix == Sale::TAXFREEPREFIX
                        ? $customer->price_list_id_INX
                        : $customer->price_list_id_INV;
                }

                // Calculate sale totals
                $data['sub_total'] = $subTotal;
                $data['sub_total_usd'] = $subTotalUsd;
                $data['total'] = $subTotal + $saleTotalTax - $additionalDiscount;
                $data['total_usd'] = $subTotalUsd + $saleTotalTaxUsd - $additionalDiscountUsd;
                $data['total_profit'] = $totalProfit - $additionalDiscountUsd;
                $data['total_volume_cbm'] = $totalVolumeCbm;
                $data['total_weight_kg'] = $totalWeightKg;

                // Get IDs of items in the request
                $requestItemIds = collect($items)->pluck('id')->filter()->toArray();

                // Handle removed items - restore inventory explicitly before bulk delete
                $itemsToDelete = $sale->items()->whereNotIn('id', $requestItemIds)->get();
                foreach ($itemsToDelete as $itemToDelete) {
                    InventoryService::add(
                        $itemToDelete->item_id,
                        $sale->warehouse_id,
                        $itemToDelete->quantity,
                        "Sale #{$sale->code} - Item removed"
                    );
                }
                // Bulk delete removed items (inventory already restored above)
                $sale->items()->whereNotIn('id', $requestItemIds)->delete();

                // Update or create items
                foreach ($items as $itemData) {
                    if (isset($itemData['id']) && $itemData['id']) {
                        // Update existing item - must use model instance to trigger events
                        $saleItem = $sale->items()->find($itemData['id']);
                        if ($saleItem) {
                            $saleItem->update($itemData);
                        }
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

            // Then soft delete the sale order (sale model will hand item delete)
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

        CustomersHelper::removeBalance(Customer::find($sale->customer_id), $sale->total_usd);

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
        abort(401);
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
            } else {
                // If employee not found, return no results
                $query->whereRaw('1 = 0');
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
