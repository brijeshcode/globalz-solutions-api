<?php

namespace App\Http\Requests\Api\Customers;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;

class ProformaInvoiceUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleHelper::canAdmin() || RoleHelper::isSalesman();
    }

    public function rules(): array
    {
        return [
            'date'                         => 'sometimes|required|date',
            'prefix'                       => 'sometimes|required|in:PINV,PINX',
            'customer_payment_term_id'     => 'nullable|exists:customer_payment_terms,id',
            'client_po_number'             => 'nullable|string|max:255',
            'currency_rate'                => 'sometimes|required|numeric|min:0',
            'sub_total'                    => 'nullable|numeric|min:0',
            'sub_total_usd'                => 'nullable|numeric|min:0',
            'discount_amount'              => 'nullable|numeric|min:0',
            'discount_amount_usd'          => 'nullable|numeric|min:0',
            'total'                        => 'sometimes|required|numeric|min:0',
            'total_usd'                    => 'sometimes|required|numeric|min:0',
            'note'                         => 'nullable|string',

            'items'                        => 'sometimes|required|array|min:1',
            'items.*.id'                   => 'nullable|exists:proforma_invoice_items,id',
            'items.*.item_id'              => 'required|exists:items,id',
            'items.*.quantity'             => 'required|numeric|min:0.01',
            'items.*.price'                => 'required|numeric|min:0',
            'items.*.ttc_price'            => 'nullable|numeric|min:0',
            'items.*.tax_percent'          => 'nullable|numeric|min:0',
            'items.*.discount_percent'     => 'nullable|numeric|min:0|max:100',
            'items.*.unit_discount_amount' => 'nullable|numeric|min:0',
            'items.*.discount_amount'      => 'nullable|numeric|min:0',
            'items.*.total_price'          => 'required|numeric|min:0',
            'items.*.total_price_usd'      => 'nullable|numeric|min:0',
            'items.*.note'                 => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'prefix.in'                    => 'Prefix must be PINV or PINX',
            'items.*.item_id.required'     => 'Item is required for each line',
            'items.*.quantity.required'    => 'Quantity is required for each line',
            'items.*.price.required'       => 'Price is required for each line',
            'items.*.total_price.required' => 'Total price is required for each line',
        ];
    }
}
