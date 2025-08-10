<?php

namespace App\Http\Requests\Api\Setups;

use Illuminate\Foundation\Http\FormRequest;

class ItemProfitMarginsStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:item_profit_margins,name',
            'margin_percentage' => 'required|numeric|min:0|max:999.99',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Profit margin name is required.',
            'name.unique' => 'This profit margin name already exists.',
            'name.max' => 'Profit margin name cannot exceed 255 characters.',
            'margin_percentage.required' => 'Margin percentage is required.',
            'margin_percentage.numeric' => 'Margin percentage must be a valid number.',
            'margin_percentage.min' => 'Margin percentage cannot be negative.',
            'margin_percentage.max' => 'Margin percentage cannot exceed 999.99.',
            'description.max' => 'Description cannot exceed 500 characters.',
        ];
    }
}