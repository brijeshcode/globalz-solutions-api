<?php

namespace App\Http\Controllers\Api\Customers;

use App\Helpers\CurrencyHelper;
use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customers\ProformaInvoiceStoreRequest;
use App\Http\Requests\Api\Customers\ProformaInvoiceUpdateRequest;
use App\Http\Resources\Api\Customers\ProformaInvoiceResource;
use App\Http\Resources\Api\Customers\SaleResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customers\ProformaInvoice;
use App\Models\Customers\ProformaInvoiceItem;
use App\Models\Customers\Sale;
use App\Models\Customers\SaleItems;
use App\Models\Items\Item;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProformaInvoicesController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = ProformaInvoice::query()
            ->with([
                'customer:id,name,code,city',
                'currency:id,name,code,symbol,decimal_places,decimal_separator,thousand_separator',
                'warehouse:id,name',
                'salesperson:id,name',
                'createdBy:id,name',
                'updatedBy:id,name',
                'statusHistories',
            ])
            ->searchable($request)
            ->sortable($request);

        if (RoleHelper::isSalesman() && !RoleHelper::isAdmin()) {
            $employee = RoleHelper::getSalesmanEmployee();
            if ($employee) {
                $query->bySalesperson($employee->id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($request->has('customer_id'))    $query->byCustomer($request->customer_id);
        if ($request->has('currency_id'))    $query->byCurrency($request->currency_id);
        if ($request->has('warehouse_id'))   $query->byWarehouse($request->warehouse_id);
        if ($request->has('salesperson_id')) $query->bySalesperson($request->salesperson_id);
        if ($request->has('prefix'))         $query->where('prefix', $request->prefix);
        if ($request->has('status'))         $query->byStatus($request->status);
        if ($request->has('date_from'))      $query->fromDate($request->date_from);
        if ($request->has('date_to'))        $query->toDate($request->date_to);

        $results = $this->applyPagination($query, $request);

        return ApiResponse::paginated('Proforma invoices retrieved successfully', $results, ProformaInvoiceResource::class);
    }

    public function store(ProformaInvoiceStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (ProformaInvoice::TAXFREEPREFIX === $data['prefix']) {
            $data['total_tax_amount']     = 0;
            $data['total_tax_amount_usd'] = 0;
            $data['invoice_tax_label']    = '';
        }

        $proforma = DB::transaction(function () use ($data) {
            $items = $data['items'];
            unset($data['items']);

            [$data, $items] = $this->calculateTotals($data, $items);

            $proforma = ProformaInvoice::create($data);

            foreach ($items as $itemData) {
                $itemData['proforma_invoice_id'] = $proforma->id;
                ProformaInvoiceItem::create($itemData);
            }

            return $proforma;
        });

        $proforma->load(['items.item', 'warehouse', 'currency', 'customer', 'salesperson', 'statusHistories']);

        return ApiResponse::store('Proforma invoice created successfully', new ProformaInvoiceResource($proforma));
    }

    public function show(ProformaInvoice $proformaInvoice): JsonResponse
    {
        $proformaInvoice->load([
            'items.item',
            'items.item.itemUnit:id,name',
            'items.item.taxCode:id,name,code,description,tax_percent',
            'warehouse:id,name',
            'currency',
            'priceList:id,code,description',
            'customer:id,name,code,address,city,mobile,mof_tax_number,google_map',
            'salesperson:id,name',
            'createdBy:id,name',
            'updatedBy:id,name',
            'approvedBy:id,name',
            'statusHistories.changedBy',
        ]);

        return ApiResponse::show('Proforma invoice retrieved successfully', new ProformaInvoiceResource($proformaInvoice));
    }

    public function update(ProformaInvoiceUpdateRequest $request, ProformaInvoice $proformaInvoice): JsonResponse
    {
        if ($proformaInvoice->isConverted()) {
            return ApiResponse::customError('Cannot update a converted proforma invoice.', 422);
        }

        $data = $request->validated();

        DB::transaction(function () use ($data, $proformaInvoice) {
            if (isset($data['items'])) {
                $items = $data['items'];
                unset($data['items']);

                [$data, $items] = $this->calculateTotals($data, $items, $proformaInvoice);

                $requestItemIds = collect($items)->pluck('id')->filter()->values()->all();
                $proformaInvoice->items()->whereNotIn('id', $requestItemIds)->delete();

                foreach ($items as $itemData) {
                    $itemData['proforma_invoice_id'] = $proformaInvoice->id;
                    if (isset($itemData['id']) && $itemData['id']) {
                        $existing = ProformaInvoiceItem::find($itemData['id']);
                        if ($existing && $existing->proforma_invoice_id === $proformaInvoice->id) {
                            unset($itemData['id']);
                            $existing->update($itemData);
                        }
                    } else {
                        unset($itemData['id']);
                        ProformaInvoiceItem::create($itemData);
                    }
                }
            }

            $proformaInvoice->update($data);
        });

        $proformaInvoice->load(['items.item', 'warehouse', 'currency']);

        return ApiResponse::update('Proforma invoice updated successfully', new ProformaInvoiceResource($proformaInvoice));
    }

    public function destroy(ProformaInvoice $proformaInvoice): JsonResponse
    {
        if ($proformaInvoice->isConverted()) {
            return ApiResponse::customError('Cannot delete a converted proforma invoice.', 422);
        }

        $proformaInvoice->delete();

        return ApiResponse::delete('Proforma invoice deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = ProformaInvoice::onlyTrashed()
            ->with(['customer:id,name,code', 'currency:id,name,code', 'warehouse:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('customer_id'))  $query->byCustomer($request->customer_id);
        if ($request->has('currency_id'))  $query->byCurrency($request->currency_id);
        if ($request->has('warehouse_id')) $query->byWarehouse($request->warehouse_id);

        $results = $this->applyPagination($query, $request);

        return ApiResponse::paginated('Trashed proforma invoices retrieved successfully', $results, ProformaInvoiceResource::class);
    }

    public function restore(int $id): JsonResponse
    {
        $proforma = ProformaInvoice::onlyTrashed()->findOrFail($id);
        $proforma->restore();
        $proforma->items()->withTrashed()->restore();

        $proforma->load(['items.item', 'warehouse', 'currency']);

        return ApiResponse::update('Proforma invoice restored successfully', new ProformaInvoiceResource($proforma));
    }

    public function forceDelete(int $id): JsonResponse
    {
        if (!RoleHelper::canAdmin()) {
            return ApiResponse::customError('Only admins can permanently delete proforma invoices.', 403);
        }

        $proforma = ProformaInvoice::onlyTrashed()->findOrFail($id);
        $proforma->items()->withTrashed()->forceDelete();
        $proforma->forceDelete();

        return ApiResponse::delete('Proforma invoice permanently deleted successfully');
    }

    public function stats(Request $request): JsonResponse
    {
        $query = ProformaInvoice::query();

        if (RoleHelper::isSalesman() && !RoleHelper::isAdmin()) {
            $employee = RoleHelper::getSalesmanEmployee();
            if ($employee) {
                $query->bySalesperson($employee->id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $stats = [
            'total'            => (clone $query)->count(),
            'by_status'        => (clone $query)->selectRaw('status, count(*) as count')->groupBy('status')->get()->mapWithKeys(fn($r) => [$r->status => $r->count]),
            'converted'        => (clone $query)->converted()->count(),
            'total_amount_usd' => (clone $query)->sum('total_usd'),
            'by_prefix'        => (clone $query)->selectRaw('prefix, count(*) as count, sum(total_usd) as total_usd')->groupBy('prefix')->get(),
        ];

        return ApiResponse::show('Proforma invoice statistics retrieved successfully', $stats);
    }

    public function changeStatus(Request $request, ProformaInvoice $proformaInvoice): JsonResponse
    {
        if ($proformaInvoice->isConverted()) {
            return ApiResponse::customError('Cannot change status of a converted proforma invoice.', 422);
        }

        $request->validate([
            'status' => 'required|in:Draft,Sent,Accepted,Rejected',
        ]);

        $proformaInvoice->update(['status' => $request->status]);

        $proformaInvoice->statusHistories()->create([
            'status'     => $request->status,
            'changed_by' => auth()->id(),
        ]);

        return ApiResponse::update('Status updated successfully', new ProformaInvoiceResource($proformaInvoice));
    }

    public function convertToSale(Request $request, ProformaInvoice $proformaInvoice): JsonResponse
    {
        if (!RoleHelper::canAdmin()) {
            return ApiResponse::customError('Only admins can convert proforma invoices to sales.', 403);
        }

        if ($proformaInvoice->isConverted()) {
            return ApiResponse::customError('This proforma invoice has already been converted.', 422);
        }

        if (!$proformaInvoice->isAccepted()) {
            return ApiResponse::customError('Only accepted proforma invoices can be converted to sales.', 422);
        }

        $request->validate([
            'approve_note' => 'nullable|string|max:1000',
        ]);

        $sale = DB::transaction(function () use ($proformaInvoice, $request) {
            $proformaInvoice->load('items');

            $salePrefix = $proformaInvoice->prefix === ProformaInvoice::TAXPREFIX
                ? Sale::TAXPREFIX
                : Sale::TAXFREEPREFIX;

            $saleData = [
                'date'                     => $proformaInvoice->date,
                'prefix'                   => $salePrefix,
                'salesperson_id'           => $proformaInvoice->salesperson_id,
                'customer_id'              => $proformaInvoice->customer_id,
                'currency_id'              => $proformaInvoice->currency_id,
                'warehouse_id'             => $proformaInvoice->warehouse_id,
                'price_list_id'            => $proformaInvoice->price_list_id,
                'customer_payment_term_id' => $proformaInvoice->customer_payment_term_id,
                'client_po_number'         => $proformaInvoice->client_po_number,
                'currency_rate'            => $proformaInvoice->currency_rate,
                'credit_limit'             => 0,
                'outStanding_balance'      => 0,
                'sub_total'                => $proformaInvoice->sub_total,
                'sub_total_usd'            => $proformaInvoice->sub_total_usd,
                'discount_amount'          => $proformaInvoice->discount_amount,
                'discount_amount_usd'      => $proformaInvoice->discount_amount_usd,
                'total'                    => $proformaInvoice->total,
                'total_usd'                => $proformaInvoice->total_usd,
                'total_profit'             => $proformaInvoice->total_profit,
                'total_volume_cbm'         => $proformaInvoice->total_volume_cbm,
                'total_weight_kg'          => $proformaInvoice->total_weight_kg,
                'total_tax_amount'         => $proformaInvoice->total_tax_amount,
                'total_tax_amount_usd'     => $proformaInvoice->total_tax_amount_usd,
                'local_curreny_rate'       => $proformaInvoice->local_curreny_rate,
                'invoice_tax_label'        => $proformaInvoice->invoice_tax_label,
                'invoice_nb1'              => $proformaInvoice->invoice_nb1,
                'invoice_nb2'              => $proformaInvoice->invoice_nb2,
                'note'                     => $proformaInvoice->note,
                'value_date'               => $proformaInvoice->value_date,
                'approved_by'              => auth()->id(),
                'approved_at'              => now(),
                'approve_note'             => $request->approve_note,
            ];

            $sale = Sale::create($saleData);

            foreach ($proformaInvoice->items as $proformaItem) {
                SaleItems::create([
                    'sale_id'                  => $sale->id,
                    'item_id'                  => $proformaItem->item_id,
                    'item_code'                => $proformaItem->item_code,
                    'quantity'                 => $proformaItem->quantity,
                    'cost_price'               => $proformaItem->cost_price,
                    'price'                    => $proformaItem->price,
                    'price_usd'                => $proformaItem->price_usd,
                    'discount_percent'         => $proformaItem->discount_percent,
                    'unit_discount_amount'     => $proformaItem->unit_discount_amount,
                    'unit_discount_amount_usd' => $proformaItem->unit_discount_amount_usd,
                    'discount_amount'          => $proformaItem->discount_amount,
                    'discount_amount_usd'      => $proformaItem->discount_amount_usd,
                    'net_sell_price'           => $proformaItem->net_sell_price,
                    'net_sell_price_usd'       => $proformaItem->net_sell_price_usd,
                    'tax_percent'              => $proformaItem->tax_percent,
                    'tax_label'                => $proformaItem->tax_label,
                    'tax_amount'               => $proformaItem->tax_amount,
                    'tax_amount_usd'           => $proformaItem->tax_amount_usd,
                    'total_tax_amount'         => $proformaItem->total_tax_amount,
                    'total_tax_amount_usd'     => $proformaItem->total_tax_amount_usd,
                    'ttc_price'                => $proformaItem->ttc_price,
                    'ttc_price_usd'            => $proformaItem->ttc_price_usd,
                    'total_net_sell_price'     => $proformaItem->total_net_sell_price,
                    'total_net_sell_price_usd' => $proformaItem->total_net_sell_price_usd,
                    'total_price'              => $proformaItem->total_price,
                    'total_price_usd'          => $proformaItem->total_price_usd,
                    'unit_profit'              => $proformaItem->unit_profit,
                    'total_profit'             => $proformaItem->total_profit,
                    'unit_volume_cbm'          => $proformaItem->unit_volume_cbm,
                    'unit_weight_kg'           => $proformaItem->unit_weight_kg,
                    'total_volume_cbm'         => $proformaItem->total_volume_cbm,
                    'total_weight_kg'          => $proformaItem->total_weight_kg,
                    'note'                     => $proformaItem->note,
                ]);
            }

            $proformaInvoice->update([
                'status'            => ProformaInvoice::STATUS_CONVERTED,
                'converted_at'      => now(),
                'converted_sale_id' => $sale->id,
            ]);

            $proformaInvoice->statusHistories()->create([
                'status'     => ProformaInvoice::STATUS_CONVERTED,
                'changed_by' => auth()->id(),
            ]);

            return $sale;
        });

        $sale->load(['saleItems.item', 'warehouse', 'currency', 'customer', 'salesperson', 'approvedBy']);

        return ApiResponse::store('Proforma invoice converted to sale successfully', new SaleResource($sale));
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function calculateTotals(array $data, array $items, ?ProformaInvoice $existing = null): array
    {
        $currencyRate = $data['currency_rate'] ?? $existing?->currency_rate ?? 1;
        $currencyId   = $data['currency_id']   ?? $existing?->currency_id;
        $prefix       = $data['prefix']        ?? $existing?->prefix;

        $totalProfit     = 0;
        $subTotal        = 0;
        $subTotalUsd     = 0;
        $saleTotalTax    = 0;
        $saleTotalTaxUsd = 0;
        $totalVolumeCbm  = 0;
        $totalWeightKg   = 0;

        foreach ($items as $index => $itemData) {
            if (!isset($itemData['item_id'])) continue;

            $item     = Item::with('itemPrice')->find($itemData['item_id']);
            $items[$index]['item_code'] = $item?->code ?? $itemData['item_code'] ?? null;
            $costPrice = $item?->itemPrice?->price_usd ?? 0;

            $sellingPrice    = $itemData['price'] ?? 0;
            $quantity        = $itemData['quantity'] ?? 0;
            $discountPercent = $itemData['discount_percent'] ?? 0;
            $taxPercent      = ($prefix === ProformaInvoice::TAXFREEPREFIX)
                ? 0
                : ($itemData['tax_percent'] ?? 0);

            if ($prefix === ProformaInvoice::TAXFREEPREFIX) {
                $items[$index]['tax_percent']    = 0;
                $items[$index]['tax_amount']     = 0;
                $items[$index]['tax_amount_usd'] = 0;
                $items[$index]['tax_label']      = '';
            }

            $sellingPriceUsd           = CurrencyHelper::toUsd($currencyId, $sellingPrice, $currencyRate);
            $unitDiscountAmount         = $sellingPrice * ($discountPercent / 100);
            $unitDiscountAmountUsd      = $sellingPriceUsd * ($discountPercent / 100);
            $discountAmount             = $unitDiscountAmount * $quantity;
            $discountAmountUsd          = $unitDiscountAmountUsd * $quantity;
            $netSellPrice               = $sellingPrice - $unitDiscountAmount;
            $netSellPriceUsd            = $sellingPriceUsd - $unitDiscountAmountUsd;
            $taxAmount                  = $taxPercent > 0 ? $netSellPrice * ($taxPercent / 100) : 0;
            $taxAmountUsd               = $taxPercent > 0 ? $netSellPriceUsd * ($taxPercent / 100) : 0;
            $ttcPrice                   = $netSellPrice + $taxAmount;
            $ttcPriceUsd                = $netSellPriceUsd + $taxAmountUsd;
            $totalNetSellPrice          = $netSellPrice * $quantity;
            $totalNetSellPriceUsd       = $netSellPriceUsd * $quantity;
            $totalTaxAmount             = $taxAmount * $quantity;
            $totalTaxAmountUsd          = $taxAmountUsd * $quantity;
            $totalPrice                 = $ttcPrice * $quantity;
            $totalPriceUsd              = $ttcPriceUsd * $quantity;
            $unitProfit                 = $netSellPriceUsd - $costPrice;
            $itemTotalProfit            = $unitProfit * $quantity;

            $items[$index]['cost_price']               = $costPrice;
            $items[$index]['price_usd']                = $sellingPriceUsd;
            $items[$index]['discount_percent']         = $discountPercent;
            $items[$index]['unit_discount_amount']     = $unitDiscountAmount;
            $items[$index]['unit_discount_amount_usd'] = $unitDiscountAmountUsd;
            $items[$index]['discount_amount']          = $discountAmount;
            $items[$index]['discount_amount_usd']      = $discountAmountUsd;
            $items[$index]['net_sell_price']           = $netSellPrice;
            $items[$index]['net_sell_price_usd']       = $netSellPriceUsd;
            $items[$index]['tax_percent']              = $taxPercent;
            $items[$index]['tax_amount']               = $taxAmount;
            $items[$index]['tax_amount_usd']           = $taxAmountUsd;
            $items[$index]['ttc_price']                = $ttcPrice;
            $items[$index]['ttc_price_usd']            = $ttcPriceUsd;
            $items[$index]['total_net_sell_price']     = $totalNetSellPrice;
            $items[$index]['total_net_sell_price_usd'] = $totalNetSellPriceUsd;
            $items[$index]['total_tax_amount']         = $totalTaxAmount;
            $items[$index]['total_tax_amount_usd']     = $totalTaxAmountUsd;
            $items[$index]['total_price']              = $totalPrice;
            $items[$index]['total_price_usd']          = $totalPriceUsd;
            $items[$index]['unit_profit']              = $unitProfit;
            $items[$index]['total_profit']             = $itemTotalProfit;

            $totalProfit     += $itemTotalProfit;
            $subTotal        += $totalNetSellPrice;
            $subTotalUsd     += $totalNetSellPriceUsd;
            $saleTotalTax    += $totalTaxAmount;
            $saleTotalTaxUsd += $totalTaxAmountUsd;
            $totalVolumeCbm  += $itemData['total_volume_cbm'] ?? 0;
            $totalWeightKg   += $itemData['total_weight_kg'] ?? 0;
        }

        $additionalDiscount    = $data['discount_amount']     ?? 0;
        $additionalDiscountUsd = $data['discount_amount_usd'] ?? 0;

        if ($prefix === ProformaInvoice::TAXFREEPREFIX) {
            $saleTotalTax    = 0;
            $saleTotalTaxUsd = 0;
            $data['total_tax_amount']     = 0;
            $data['total_tax_amount_usd'] = 0;
            $data['invoice_tax_label']    = '';
        } else {
            $data['total_tax_amount']     = $saleTotalTax;
            $data['total_tax_amount_usd'] = $saleTotalTaxUsd;
        }

        $data['sub_total']        = $subTotal;
        $data['sub_total_usd']    = $subTotalUsd;
        $data['total']            = $subTotal + $saleTotalTax - $additionalDiscount;
        $data['total_usd']        = $subTotalUsd + $saleTotalTaxUsd - $additionalDiscountUsd;
        $data['total_profit']     = $totalProfit - $additionalDiscountUsd;
        $data['total_volume_cbm'] = $totalVolumeCbm;
        $data['total_weight_kg']  = $totalWeightKg;

        return [$data, $items];
    }
}
