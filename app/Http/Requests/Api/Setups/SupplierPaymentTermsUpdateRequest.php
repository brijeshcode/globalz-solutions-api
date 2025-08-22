<?php

namespace App\Http\Requests\Api\Setups;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierPaymentTermsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('supplier_payment_terms', 'name')->ignore($this->supplierPaymentTerm)
            ],
            'description' => 'nullable|string|max:500',
            'days' => 'nullable|integer|min:-365|max:365',
            'type' => [
                'nullable',
                Rule::in(['net', 'due_on_receipt', 'cash_on_delivery', 'advance', 'credit'])
            ],
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'discount_days' => 'nullable|integer|min:0|max:365',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Payment term name is required.',
            'name.unique' => 'This payment term name already exists.',
            'name.max' => 'Payment term name cannot exceed 255 characters.',
            'description.max' => 'Description cannot exceed 500 characters.',
            'days.integer' => 'Days must be a valid number.',
            'days.min' => 'Days cannot be less than -365.',
            'days.max' => 'Days cannot exceed 365.',
            'type.in' => 'Invalid payment term type selected.',
            'discount_percentage.numeric' => 'Discount percentage must be a valid number.',
            'discount_percentage.min' => 'Discount percentage cannot be negative.',
            'discount_percentage.max' => 'Discount percentage cannot exceed 100%.',
            'discount_days.integer' => 'Discount days must be a valid number.',
            'discount_days.min' => 'Discount days cannot be negative.',
            'discount_days.max' => 'Discount days cannot exceed 365.',
        ];
    }
}