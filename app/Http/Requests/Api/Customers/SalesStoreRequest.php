<?php

namespace App\Http\Requests\Api\Customers;

use Illuminate\Foundation\Http\FormRequest;

class SalesStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
