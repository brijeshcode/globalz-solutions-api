<?php

namespace App\Http\Requests\Api\Customers;

use App\Helpers\CurrencyHelper;
use App\Helpers\RoleHelper;
use App\Helpers\SettingsHelper;
use App\Models\Item;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SalesStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (SettingsHelper::get('sale_settings', 'block_new_sale', false)) {
            return false;
        }

        return RoleHelper::canAdmin();
    }

    public function failedAuthorization(): never
    {
        $message = SettingsHelper::get('sale_settings', 'block_new_sale', false)
            ? 'Creating new sales is currently disabled by the administrator.'
            : 'This action is unauthorized.';

        throw new HttpResponseException(
            response()->json(['message' => $message], 403)
        );
    }

    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'prefix' => 'required|in:INV,INX',
            'salesperson_id' => 'nullable|exists:employees,id',
            'customer_id' => 'nullable|exists:customers,id',
            'currency_id' => 'required|exists:currencies,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'customer_payment_term_id' => 'nullable|exists:customer_payment_terms,id',
            'client_po_number' => 'nullable|string|max:255',
            'currency_rate' => 'required|numeric|min:0',
            'credit_limit' => 'nullable|numeric|min:0',
            'outStanding_balance' => 'nullable|numeric|min:0',
            'sub_total' => 'nullable|numeric|min:0',
            'sub_total_usd' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_amount_usd' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'total_usd' => 'required|numeric|min:0',
            'note' => 'nullable|string',

            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.supplier_id' => 'nullable|exists:suppliers,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.ttc_price' => 'nullable|numeric|min:0',
            'items.*.tax_percent' => 'nullable|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.unit_discount_amount' => 'nullable|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.total_price' => 'required|numeric|min:0',
            'items.*.total_price_usd' => 'nullable|numeric|min:0',
            'items.*.note' => 'nullable|string',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $allowBelowCost = RoleHelper::isSuperAdmin()
                ? SettingsHelper::get('sale_settings', 'allow_super_admin_sell_below_cost', false)
                : SettingsHelper::get('sale_settings', 'allow_admin_sell_below_cost', false);

            if ($allowBelowCost) {
                return;
            }

            $currencyId  = (int) $this->input('currency_id');
            $currencyRate = (float) ($this->input('currency_rate') ?? 1);

            foreach ($this->input('items', []) as $index => $itemData) {
                $itemId = $itemData['item_id'] ?? null;
                if (! $itemId) {
                    continue;
                }

                $item = Item::with('itemPrice')->find($itemId);
                $costPriceUsd = $item?->itemPrice?->price_usd ?? 0;

                if ($costPriceUsd <= 0) {
                    continue;
                }

                $sellingPrice    = (float) ($itemData['price'] ?? 0);
                $discountPercent = (float) ($itemData['discount_percent'] ?? 0);
                $sellingPriceUsd = CurrencyHelper::toUsd($currencyId, $sellingPrice, $currencyRate);
                $netSellPriceUsd = $sellingPriceUsd * (1 - $discountPercent / 100);

                if ($netSellPriceUsd < $costPriceUsd) {
                    $validator->errors()->add(
                        "items.$index.price",
                        'Item ' . ($index + 1) . ': selling price cannot be below cost price.'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Sale date is required',
            'prefix.required' => 'Invoice prefix is required',
            'prefix.in' => 'Invoice prefix must be either INV or INX',
            'currency_id.required' => 'Currency is required',
            'warehouse_id.required' => 'Warehouse is required',
            'currency_rate.required' => 'Currency rate is required',
            'total.required' => 'Total amount is required',
            'total_usd.required' => 'Total amount in USD is required',
            'items.required' => 'At least one sale item is required',
            'items.*.item_id.required' => 'Item is required for each sale item',
            'items.*.quantity.required' => 'Quantity is required for each sale item',
            'items.*.price.required' => 'Price is required for each sale item',
            'items.*.total_price.required' => 'Total price is required for each sale item',
        ];
    }
}
