<?php

namespace App\Http\Requests\Api\Suppliers;

use Illuminate\Foundation\Http\FormRequest;

class PurchasesStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'currency_id' => 'required|integer|exists:currencies,id',
            'account_id' => 'nullable|integer|exists:accounts,id',
            'supplier_invoice_number' => 'nullable|string|max:255',
            'currency_rate' => 'required|numeric|min:0.000001|max:999999.999999',
            'final_total_usd' => 'required|numeric|min:0.000000|max:999999.999999',
            'total_usd' => 'required|numeric|min:0.000000|max:999999.999999',
            'shipping_fee_usd' => 'nullable|numeric|min:0|max:999999.9999',
            'customs_fee_usd' => 'nullable|numeric|min:0|max:999999.9999',
            'other_fee_usd' => 'nullable|numeric|min:0|max:999999.9999',
            'tax_usd' => 'nullable|numeric|min:0|max:999999.9999',
            'shipping_fee_usd_percent' => 'nullable|numeric|min:0|max:100',
            'customs_fee_usd_percent' => 'nullable|numeric|min:0|max:100',
            'other_fee_usd_percent' => 'nullable|numeric|min:0|max:100',
            'tax_usd_percent' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0|max:999999.9999',
            'discount_amount_usd' => 'nullable|numeric|min:0|max:999999.9999',
            'note' => 'nullable|string|max:1000',
            
            // Purchase items
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required_with:items|integer|exists:items,id',
            'items.*.price' => 'required_with:items|numeric|min:0|max:999999.9999',
            'items.*.quantity' => 'required_with:items|numeric|min:0.0001|max:999999.9999',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.discount_amount' => 'nullable|numeric|min:0|max:999999.9999',
            'items.*.note' => 'nullable|string|max:1000',
            
            // Documents
            'documents' => 'nullable|array|max:15',
            'documents.*' => 'nullable|file|mimes:jpg,jpeg,png,gif,bmp,webp,pdf,doc,docx,txt|max:10240', // 10MB max
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Supplier is required.',
            'warehouse_id.required' => 'Warehouse is required.',
            'currency_id.required' => 'Currency is required.',
            'date.required' => 'Purchase date is required.',
            'currency_rate.required' => 'Currency rate is required.',
            'currency_rate.min' => 'Currency rate must be greater than 0.',
            'items.*.item_id.required_with' => 'Item is required for each purchase item.',
            'items.*.price.required_with' => 'Price is required for each purchase item.',
            'items.*.quantity.required_with' => 'Quantity is required for each purchase item.',
            'items.*.quantity.min' => 'Quantity must be greater than 0.',
            'items.required' => 'At least one item is required for the purchase.',
            'items.min' => 'At least one item is required for the purchase.',
            'documents.*.max' => 'Each document must not exceed 10MB.',
            'documents.*.mimes' => 'Documents must be of type: jpg, jpeg, png, gif, bmp, webp, pdf, doc, docx, txt.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Set default values for optional fields
        $this->merge([
            'shipping_fee_usd' => $this->shipping_fee_usd ?? 0,
            'customs_fee_usd' => $this->customs_fee_usd ?? 0,
            'other_fee_usd' => $this->other_fee_usd ?? 0,
            'tax_usd' => $this->tax_usd ?? 0,
            'shipping_fee_usd_percent' => $this->shipping_fee_usd_percent ?? 0,
            'customs_fee_usd_percent' => $this->customs_fee_usd_percent ?? 0,
            'other_fee_usd_percent' => $this->other_fee_usd_percent ?? 0,
            'tax_usd_percent' => $this->tax_usd_percent ?? 0,
            'discount_amount' => $this->discount_amount ?? 0,
            'discount_amount_usd' => $this->discount_amount_usd ?? 0,
        ]);
    }
}
