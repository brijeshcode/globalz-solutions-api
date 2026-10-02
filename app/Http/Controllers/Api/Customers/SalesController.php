<?php

namespace App\Http\Controllers\Api\Customers;

use App\Helpers\CommonHelper;
use App\Helpers\CurrencyHelper;
use App\Helpers\CustomersHelper;
use App\Helpers\FeatureHelper;
use App\Helpers\RoleHelper;
use App\Helpers\SettingsHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customers\SalesStoreRequest;
use App\Http\Requests\Api\Customers\SalesUpdateRequest;
use App\Http\Resources\Api\Customers\SaleResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customers\Sale;
use App\Models\Customers\SaleItems;
use App\Models\Customers\SaleService;
use App\Models\Customers\Customer;
use App\Models\Inventory\ItemPriceHistory;
use App\Models\Items\Item;
use App\Models\Items\PriceList;
use App\Models\Setups\Warehouse;
use App\Services\Customers\SaleOfferService;
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
        $query = $this->saleQuery($request)
            ->withCount(['saleItems as offer_items_count' => fn ($q) => $q->whereNotNull('item_offer_id')]);

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

        $stats = $this->buildStats($this->saleQuery($request));

        $sales = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Sales retrieved successfully',
            $sales,
            SaleResource::class,
            $stats
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

        // Service-only sales may omit the warehouse; fall back to the default one.
        if (empty($data['warehouse_id'])) {
            $data['warehouse_id'] = Warehouse::where('is_default', true)->value('id');
        }

        // Validate & normalize any applied offer lines before the calculation loop.
        if (!empty($data['items'])) {
            $data['items'] = app(SaleOfferService::class)->normalize($data['items']);
        }

        if (Sale::TAXFREEPREFIX == $data['prefix']) {
            $data['total_tax_amount'] = 0;
            $data['total_tax_amount_usd'] = 0;
            $data['invoice_tax_label'] = '';
        }

        $sale = DB::transaction(function () use ($data) {
            $saleItems = $data['items'] ?? [];
            // Services only when the feature is enabled; otherwise silently ignored (never blocks the sale).
            $saleServices = FeatureHelper::isSaleServices() ? ($data['services'] ?? []) : [];
            unset($data['items'], $data['services']);

            $totalProfit = 0;
            $subTotal = 0;
            $subTotalUsd = 0;
            $saleTotalTax = 0;
            $saleTotalTaxUsd = 0;
            $totalVolumeCbm = 0;
            $totalWeightKg = 0;

            // Per-type breakout accumulators
            $itemsTotal = 0;
            $itemsTotalUsd = 0;
            $itemsProfit = 0;
            $itemsTax = 0;
            $itemsTaxUsd = 0;
            $servicesTotal = 0;
            $servicesTotalUsd = 0;
            $servicesProfit = 0;
            $servicesTax = 0;
            $servicesTaxUsd = 0;

            // Calculate totals from sale items
            $currencyRate = $data['currency_rate'] ?? 1;
            $currencyId = $data['currency_id'];
            foreach ($saleItems as $index => $itemData) {
                if (isset($itemData['item_id'])) {
                    /** @var Item|null $item */
                    $item = Item::with('itemPrice')->find($itemData['item_id']);
                    $saleItems[$index]['item_code'] = $item?->code ?? $itemData['item_code'] ?? null;

                    // Get cost price from item's price (already in USD)
                    $itemPrice = $item?->itemPrice;
                    $costPrice = $itemPrice?->price_usd ?? 0;
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
                    $taxPercent = $saleItems[$index]['tax_percent'] ?? $itemData['tax_percent'] ?? 0;

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
                    $saleItems[$index]['cost_history_id'] = ItemPriceHistory::currentRowIdFor($itemData['item_id']);
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

                    $itemsTotal += $totalPrice;
                    $itemsTotalUsd += $totalPriceUsd;
                    $itemsProfit += $itemTotalProfit;
                    $itemsTax += $totalTaxAmount;
                    $itemsTaxUsd += $totalTaxAmountUsd;
                }
            }

            // Service lines: cost is always 0 (pure profit); server takes unit_price and
            // computes the rest. Same formula as items, no stock/volume/weight.
            foreach ($saleServices as $index => $serviceData) {
                $sellingPrice = $serviceData['unit_price'] ?? 0;
                $quantity = $serviceData['quantity'] ?? 0;
                $discountPercent = $serviceData['discount_percent'] ?? 0;
                $taxPercent = (Sale::TAXFREEPREFIX == $data['prefix']) ? 0 : ($serviceData['tax_percent'] ?? 0);

                $sellingPriceUsd = CurrencyHelper::toUsd($currencyId, $sellingPrice, $currencyRate);

                $costPrice = 0;

                $unitDiscountAmount = $sellingPrice * ($discountPercent / 100);
                $unitDiscountAmountUsd = $sellingPriceUsd * ($discountPercent / 100);
                $discountAmount = $unitDiscountAmount * $quantity;
                $discountAmountUsd = $unitDiscountAmountUsd * $quantity;
                $netSellPrice = $sellingPrice - $unitDiscountAmount;
                $netSellPriceUsd = $sellingPriceUsd - $unitDiscountAmountUsd;
                $taxAmount = $taxPercent > 0 ? $netSellPrice * ($taxPercent / 100) : 0;
                $taxAmountUsd = $taxPercent > 0 ? $netSellPriceUsd * ($taxPercent / 100) : 0;
                $ttcPrice = $netSellPrice + $taxAmount;
                $ttcPriceUsd = $netSellPriceUsd + $taxAmountUsd;
                $totalNetSellPrice = $netSellPrice * $quantity;
                $totalNetSellPriceUsd = $netSellPriceUsd * $quantity;
                $totalTaxAmount = $taxAmount * $quantity;
                $totalTaxAmountUsd = $taxAmountUsd * $quantity;
                $totalPrice = $ttcPrice * $quantity;
                $totalPriceUsd = $ttcPriceUsd * $quantity;
                $unitProfit = $netSellPriceUsd - $costPrice;
                $serviceTotalProfit = $unitProfit * $quantity;

                $saleServices[$index]['date'] = $serviceData['date'] ?? $data['date'];
                $saleServices[$index]['unit_cost_price'] = $costPrice;
                $saleServices[$index]['unit_price'] = $sellingPrice;
                $saleServices[$index]['unit_price_usd'] = $sellingPriceUsd;
                $saleServices[$index]['discount_percent'] = $discountPercent;
                $saleServices[$index]['unit_discount_amount'] = $unitDiscountAmount;
                $saleServices[$index]['unit_discount_amount_usd'] = $unitDiscountAmountUsd;
                $saleServices[$index]['total_discount_amount'] = $discountAmount;
                $saleServices[$index]['total_discount_amount_usd'] = $discountAmountUsd;
                $saleServices[$index]['unit_net_sell_price'] = $netSellPrice;
                $saleServices[$index]['unit_net_sell_price_usd'] = $netSellPriceUsd;
                $saleServices[$index]['tax_percent'] = $taxPercent;
                $saleServices[$index]['unit_tax_amount'] = $taxAmount;
                $saleServices[$index]['unit_tax_amount_usd'] = $taxAmountUsd;
                $saleServices[$index]['unit_ttc_price'] = $ttcPrice;
                $saleServices[$index]['unit_ttc_price_usd'] = $ttcPriceUsd;
                $saleServices[$index]['total_net_sell_price'] = $totalNetSellPrice;
                $saleServices[$index]['total_net_sell_price_usd'] = $totalNetSellPriceUsd;
                $saleServices[$index]['total_tax_amount'] = $totalTaxAmount;
                $saleServices[$index]['total_tax_amount_usd'] = $totalTaxAmountUsd;
                $saleServices[$index]['total_price'] = $totalPrice;
                $saleServices[$index]['total_price_usd'] = $totalPriceUsd;
                $saleServices[$index]['unit_profit'] = $unitProfit;
                $saleServices[$index]['total_profit'] = $serviceTotalProfit;
                if (Sale::TAXFREEPREFIX == $data['prefix']) {
                    $saleServices[$index]['tax_label'] = '';
                }

                $totalProfit += $serviceTotalProfit;
                $subTotal += $totalNetSellPrice;
                $subTotalUsd += $totalNetSellPriceUsd;
                $saleTotalTax += $totalTaxAmount;
                $saleTotalTaxUsd += $totalTaxAmountUsd;
                $servicesTotal += $totalPrice;
                $servicesTotalUsd += $totalPriceUsd;
                $servicesProfit += $serviceTotalProfit;
                $servicesTax += $totalTaxAmount;
                $servicesTaxUsd += $totalTaxAmountUsd;
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

            // Breakout columns only when the feature is on — disabled tenants never write them.
            if (FeatureHelper::isSaleServices()) {
                $data['items_total'] = $itemsTotal;
                $data['items_total_usd'] = $itemsTotalUsd;
                $data['items_profit'] = $itemsProfit;
                $data['items_total_tax_amount'] = $itemsTax;
                $data['items_total_tax_amount_usd'] = $itemsTaxUsd;
                $data['services_total'] = $servicesTotal;
                $data['services_total_usd'] = $servicesTotalUsd;
                $data['services_profit'] = $servicesProfit;
                $data['services_total_tax_amount'] = $servicesTax;
                $data['services_total_tax_amount_usd'] = $servicesTaxUsd;
            }

            $sale = Sale::create($data);

            foreach ($saleItems as $itemData) {
                $itemData['sale_id'] = $sale->id;
                SaleItems::create($itemData);
            }

            foreach ($saleServices as $serviceData) {
                $serviceData['sale_id'] = $sale->id;
                SaleService::create($serviceData);
            }

            return $sale;
        });

        $sale->load(['saleItems.item', 'saleServices.service', 'warehouse', 'currency']);

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

        $sale->load(['saleItems.item', 'saleItems.item.itemUnit:id,name', 'saleItems.item.taxCode:id,name,code,description,tax_percent', 'warehouse:id,name', 'currency', 'priceList:id,code,description', 'customer:id,name,code,address,city,mobile,mof_tax_number,google_map', 'salesperson:id,name', 'createdBy:id,name', 'updatedBy:id,name', 'approvedBy:id,name', 'statusHistories.changedBy', 'statusHistories.car']);

        if (FeatureHelper::isSaleServices()) {
            $sale->load(['saleServices.service']);
        }

        return ApiResponse::show(
            'Sale retrieved successfully',
            new SaleResource($sale)
        );
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
            $hasItems = isset($data['items']);
            $hasServices = FeatureHelper::isSaleServices() && isset($data['services']);

            if ($hasItems || $hasServices) {
                // Shared accumulators across items + services (combined sale supported).
                $totalProfit = 0;
                $subTotal = 0;
                $subTotalUsd = 0;
                $saleTotalTax = 0;
                $saleTotalTaxUsd = 0;
                $totalVolumeCbm = 0;
                $totalWeightKg = 0;
                $itemsTotal = 0;
                $itemsTotalUsd = 0;
                $itemsProfit = 0;
                $itemsTax = 0;
                $itemsTaxUsd = 0;
                $servicesTotal = 0;
                $servicesTotalUsd = 0;
                $servicesProfit = 0;
                $servicesTax = 0;
                $servicesTaxUsd = 0;

                $currencyRate = $data['currency_rate'] ?? $sale->currency_rate ?? 1;
                $prefix = $data['prefix'] ?? $sale->prefix;
            }

            if ($hasItems) {
                $data['items'] = app(SaleOfferService::class)->normalize($data['items']);
                $saleItems = $data['items'];
                unset($data['items']);

                foreach ($saleItems as $index => $itemData) {
                    if (isset($itemData['item_id'])) {
                        /** @var Item|null $item */
                        $item = Item::with('itemPrice')->find($itemData['item_id']);
                        $saleItems[$index]['item_code'] = $item?->code ?? $itemData['item_code'] ?? null;

                        // Get cost price from item's price (already in USD)
                        $itemPrice = $item?->itemPrice;
                        $costPrice = $itemPrice?->price_usd ?? 0;

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
                            $saleItems[$index]['tax_label'] = '';
                        }

                        // Assign all calculated values to sale item
                        $saleItems[$index]['cost_price'] = $costPrice;
                        $saleItems[$index]['cost_history_id'] = ItemPriceHistory::currentRowIdFor($itemData['item_id']);
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
                        $itemsTotal += $totalPrice;
                        $itemsTotalUsd += $totalPriceUsd;
                        $itemsProfit += $itemTotalProfit;
                        $itemsTax += $totalTaxAmount;
                        $itemsTaxUsd += $totalTaxAmountUsd;
                    }
                }

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

            if ($hasServices) {
                $saleServices = $data['services'];
                unset($data['services']);

                foreach ($saleServices as $index => $serviceData) {
                    $sellingPrice = $serviceData['unit_price'] ?? 0;
                    $quantity = $serviceData['quantity'] ?? 0;
                    $discountPercent = $serviceData['discount_percent'] ?? 0;
                    $taxPercent = ($prefix == Sale::TAXFREEPREFIX) ? 0 : ($serviceData['tax_percent'] ?? 0);

                    $sellingPriceUsd = CurrencyHelper::toUsd($sale->currency_id, $sellingPrice, $currencyRate);

                    $costPrice = 0;

                    $unitDiscountAmount = $sellingPrice * ($discountPercent / 100);
                    $unitDiscountAmountUsd = $sellingPriceUsd * ($discountPercent / 100);
                    $discountAmount = $unitDiscountAmount * $quantity;
                    $discountAmountUsd = $unitDiscountAmountUsd * $quantity;
                    $netSellPrice = $sellingPrice - $unitDiscountAmount;
                    $netSellPriceUsd = $sellingPriceUsd - $unitDiscountAmountUsd;
                    $taxAmount = $taxPercent > 0 ? $netSellPrice * ($taxPercent / 100) : 0;
                    $taxAmountUsd = $taxPercent > 0 ? $netSellPriceUsd * ($taxPercent / 100) : 0;
                    $ttcPrice = $netSellPrice + $taxAmount;
                    $ttcPriceUsd = $netSellPriceUsd + $taxAmountUsd;
                    $totalNetSellPrice = $netSellPrice * $quantity;
                    $totalNetSellPriceUsd = $netSellPriceUsd * $quantity;
                    $totalTaxAmount = $taxAmount * $quantity;
                    $totalTaxAmountUsd = $taxAmountUsd * $quantity;
                    $totalPrice = $ttcPrice * $quantity;
                    $totalPriceUsd = $ttcPriceUsd * $quantity;
                    $unitProfit = $netSellPriceUsd - $costPrice;
                    $serviceTotalProfit = $unitProfit * $quantity;

                    $saleServices[$index]['date'] = $serviceData['date'] ?? ($data['date'] ?? $sale->date);
                    $saleServices[$index]['unit_cost_price'] = $costPrice;
                    $saleServices[$index]['unit_price'] = $sellingPrice;
                    $saleServices[$index]['unit_price_usd'] = $sellingPriceUsd;
                    $saleServices[$index]['discount_percent'] = $discountPercent;
                    $saleServices[$index]['unit_discount_amount'] = $unitDiscountAmount;
                    $saleServices[$index]['unit_discount_amount_usd'] = $unitDiscountAmountUsd;
                    $saleServices[$index]['total_discount_amount'] = $discountAmount;
                    $saleServices[$index]['total_discount_amount_usd'] = $discountAmountUsd;
                    $saleServices[$index]['unit_net_sell_price'] = $netSellPrice;
                    $saleServices[$index]['unit_net_sell_price_usd'] = $netSellPriceUsd;
                    $saleServices[$index]['tax_percent'] = $taxPercent;
                    $saleServices[$index]['unit_tax_amount'] = $taxAmount;
                    $saleServices[$index]['unit_tax_amount_usd'] = $taxAmountUsd;
                    $saleServices[$index]['unit_ttc_price'] = $ttcPrice;
                    $saleServices[$index]['unit_ttc_price_usd'] = $ttcPriceUsd;
                    $saleServices[$index]['total_net_sell_price'] = $totalNetSellPrice;
                    $saleServices[$index]['total_net_sell_price_usd'] = $totalNetSellPriceUsd;
                    $saleServices[$index]['total_tax_amount'] = $totalTaxAmount;
                    $saleServices[$index]['total_tax_amount_usd'] = $totalTaxAmountUsd;
                    $saleServices[$index]['total_price'] = $totalPrice;
                    $saleServices[$index]['total_price_usd'] = $totalPriceUsd;
                    $saleServices[$index]['unit_profit'] = $unitProfit;
                    $saleServices[$index]['total_profit'] = $serviceTotalProfit;
                    if ($prefix == Sale::TAXFREEPREFIX) {
                        $saleServices[$index]['tax_label'] = '';
                    }

                    $totalProfit += $serviceTotalProfit;
                    $subTotal += $totalNetSellPrice;
                    $subTotalUsd += $totalNetSellPriceUsd;
                    $saleTotalTax += $totalTaxAmount;
                    $saleTotalTaxUsd += $totalTaxAmountUsd;
                    $servicesProfit += $serviceTotalProfit;
                    $servicesTotal += $totalPrice;
                    $servicesTotalUsd += $totalPriceUsd;
                    $servicesTax += $totalTaxAmount;
                    $servicesTaxUsd += $totalTaxAmountUsd;
                }

                // Remove services no longer present in the request (no inventory to restore).
                $requestServiceIds = collect($saleServices)->pluck('id')->filter()->values()->all();
                $sale->saleServices()->whereNotIn('id', $requestServiceIds)->delete();

                foreach ($saleServices as $serviceData) {
                    $serviceData['sale_id'] = $sale->id;

                    if (isset($serviceData['id']) && $serviceData['id']) {
                        $saleService = SaleService::find($serviceData['id']);
                        if ($saleService && $saleService->sale_id === $sale->id) {
                            unset($serviceData['id']);
                            $saleService->update($serviceData);
                        }
                    } else {
                        unset($serviceData['id']);
                        SaleService::create($serviceData);
                    }
                }
            }

            // Combined sale-level totals written once, after both item and service loops.
            if ($hasItems || $hasServices) {
                $additionalDiscount = $data['discount_amount'] ?? 0;
                $additionalDiscountUsd = $data['discount_amount_usd'] ?? 0;

                // Tax-free prefix: no tax anywhere, clear the label.
                if ($prefix == Sale::TAXFREEPREFIX) {
                    $saleTotalTax = 0;
                    $saleTotalTaxUsd = 0;
                    $itemsTax = 0;
                    $itemsTaxUsd = 0;
                    $servicesTax = 0;
                    $servicesTaxUsd = 0;
                    $data['invoice_tax_label'] = '';
                } else {
                    $data['invoice_tax_label'] = CommonHelper::getTaxLable();
                }

                // Update price_list_id when prefix changes
                $customer = Customer::select('price_list_id_INV', 'price_list_id_INX')->find($data['customer_id'] ?? $sale->customer_id);
                if ($customer) {
                    $customerPriceList = $prefix == Sale::TAXFREEPREFIX
                        ? $customer->price_list_id_INX
                        : $customer->price_list_id_INV;

                    if (!$customerPriceList) {
                        $defaultPriceList = $prefix == Sale::TAXFREEPREFIX
                            ? PriceList::getDefaultInx()
                            : PriceList::getDefaultInv();
                        $customerPriceList = $defaultPriceList?->id;
                    }

                    $data['price_list_id'] = $customerPriceList;
                }

                $data['sub_total'] = $subTotal;
                $data['sub_total_usd'] = $subTotalUsd;
                $data['total_tax_amount'] = $saleTotalTax;
                $data['total_tax_amount_usd'] = $saleTotalTaxUsd;
                $data['total'] = $subTotal + $saleTotalTax - $additionalDiscount;
                $data['total_usd'] = $subTotalUsd + $saleTotalTaxUsd - $additionalDiscountUsd;
                $data['total_profit'] = $totalProfit - $additionalDiscountUsd;
                $data['total_volume_cbm'] = $totalVolumeCbm;
                $data['total_weight_kg'] = $totalWeightKg;

                // Breakout columns only when the feature is on.
                if (FeatureHelper::isSaleServices()) {
                    $data['items_total'] = $itemsTotal;
                    $data['items_total_usd'] = $itemsTotalUsd;
                    $data['items_profit'] = $itemsProfit;
                    $data['items_total_tax_amount'] = $itemsTax;
                    $data['items_total_tax_amount_usd'] = $itemsTaxUsd;
                    $data['services_total'] = $servicesTotal;
                    $data['services_total_usd'] = $servicesTotalUsd;
                    $data['services_profit'] = $servicesProfit;
                    $data['services_total_tax_amount'] = $servicesTax;
                    $data['services_total_tax_amount_usd'] = $servicesTaxUsd;
                }
            }

            $sale->update($data);
        });

        $sale->load(['saleItems.item', 'saleServices.service', 'warehouse', 'currency']);

        return ApiResponse::update(
            'Sale updated successfully',
            new SaleResource($sale)
        );
    }

    public function destroy(Sale $sale): JsonResponse
    {
        if (SettingsHelper::get('sale_settings', 'block_new_sale', false)) {
            return ApiResponse::customError('Deleting sales is currently disabled by the administrator.', 403);
        }

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

        if (FeatureHelper::isSaleServices()) {
            $query->with(['saleServices.service']);
        }

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
        $sale->saleServices()->withTrashed()->restore();

        $sale->load(['saleItems.item', 'saleServices.service', 'warehouse', 'currency']);

        return ApiResponse::update(
            'Sale restored successfully',
            new SaleResource($sale)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $sale = Sale::onlyTrashed()->findOrFail($id);

        $sale->saleItems()->withTrashed()->forceDelete();
        $sale->saleServices()->withTrashed()->forceDelete();
        $sale->forceDelete();

        return ApiResponse::delete('Sale permanently deleted successfully');
    }

    public function stats(Request $request): JsonResponse
    {
        $query = $this->saleQuery($request);

        return ApiResponse::show('Sale statistics retrieved successfully', $this->buildStats($query));
    }

    private function buildStats(\Illuminate\Database\Eloquent\Builder $query): array
    {
        $trashedSales = (clone $query)->onlyTrashed()->count();

        $statusRows = (clone $query)
            ->selectRaw('status, COUNT(*) as count, SUM(total) as total_amount, SUM(total_usd) as total_amount_usd, SUM(total_tax_amount_usd) as total_tax_amount_usd')
            ->groupBy('status')
            ->get();

        return [
            'trashed_sales'        => $trashedSales,
            'total_amount'         => $statusRows->sum('total_amount'),
            'total_amount_usd'     => $statusRows->sum('total_amount_usd'),
            'total_tax_amount_usd' => $statusRows->sum('total_tax_amount_usd'),
            'sales_by_status'      => $statusRows->mapWithKeys(fn($item) => [$item->status => $item->count]),
        ];
    }

    public function changeStatus(Request $request, Sale $sale): JsonResponse
    {
        if (! $sale->isApproved()) {
            return ApiResponse::customError('Cannot change status off an un-approved sales order.', 422);
        }

        if (! RoleHelper::canWarehouseManager()) {
            return ApiResponse::customError('Only warehouse manager can change the status.', 422);
        }

        $sale->update(['status' => $request->status]);

        $historyData = [
            'status'     => $request->status,
            'changed_by' => auth()->id(),
        ];

        if ($request->status === 'Delivered' && $request->filled('car_id')) {
            $historyData['car_id'] = $request->integer('car_id');
        }

        $sale->statusHistories()->create($historyData);

        return ApiResponse::update(
            'Sale status updated successfully',
            new SaleResource($sale)
        );
    }

    public function unapprove(Sale $sale): JsonResponse
    {
        if (SettingsHelper::get('sale_settings', 'block_new_sale', false)) {
            return ApiResponse::customError('Unapproving sales is currently disabled by the administrator.', 403);
        }

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
                CustomersHelper::addBalance($customer, (float) $sale->total_usd);
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
            ->with(['saleItems.item', 'saleItems.item.itemUnit:id,name', 'saleItems.item.taxCode:id,name,code,description,tax_percent', 'warehouse:id,name', 'currency', 'priceList:id,code,description', 'customer:id,name,code,address,city,mobile,mof_tax_number', 'salesperson:id,name', 'createdBy:id,name', 'updatedBy:id,name', 'approvedBy:id,name', 'statusHistories'])
            ->approved()
            ->searchable($request)
            ;

        if (FeatureHelper::isSaleServices()) {
            $query->with(['saleServices.service']);
        }

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

        if ($request->has('prefix')) {
            $query->where('prefix', $request->prefix);
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

        
        if ($request->has('discount_checks')) {
            if($request->discount_checks == 'discount_amount')
            {
                $query->where('discount_amount', '!=', 0);
            }

            if($request->discount_checks == 'has_item_discount')
            {
                $query->whereHas('saleItems', fn ($q) => $q->where('discount_amount', '!=', 0));
            }

            if($request->discount_checks == 'both_discount')
            {
                $query->where('discount_amount', '!=', 0)
                    ->whereHas('saleItems', fn ($q) => $q->where('discount_amount', '!=', 0));
            }
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
