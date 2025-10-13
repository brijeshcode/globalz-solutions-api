<?php

namespace App\Http\Requests\Api\Setups;

use Illuminate\Foundation\Http\FormRequest;

class CurrenciesStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:currencies,name',
            'code' => 'required|string|size:3|unique:currencies,code',
            'symbol' => 'nullable|string|max:10',
            'symbol_position' => 'nullable|string|in:before,after',
            'calculation_type' => 'required|in:multiply,divide',
            'decimal_places' => 'nullable|integer|min:0|max:10',
            'decimal_separator' => 'nullable|string|max:5',
            'thousand_separator' => 'nullable|string|max:5',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Currency name is required.',
            'name.unique' => 'This currency name already exists.',
            'name.max' => 'Currency name cannot exceed 255 characters.',
            'code.required' => 'Currency code is required.',
            'code.size' => 'Currency code must be exactly 3 characters.',
            'code.unique' => 'This currency code already exists.',
            'symbol.max' => 'Currency symbol cannot exceed 10 characters.',
            'symbol_position.in' => 'Symbol position must be either before or after.',
            'decimal_places.integer' => 'Decimal places must be a number.',
            'decimal_places.min' => 'Decimal places cannot be negative.',
            'decimal_places.max' => 'Decimal places cannot exceed 10.',
            'decimal_separator.max' => 'Decimal separator cannot exceed 5 characters.',
            'thousand_separator.max' => 'Thousand separator cannot exceed 5 characters.',
        ];
    }
}