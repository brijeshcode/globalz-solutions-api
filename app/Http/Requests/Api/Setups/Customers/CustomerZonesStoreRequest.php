<?php

namespace App\Http\Requests\Api\Setups\Customers;

use Illuminate\Foundation\Http\FormRequest;

class CustomerZonesStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:customer_zones,name',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Customer zone name is required.',
            'name.unique' => 'This customer zone name already exists.',
            'name.max' => 'Customer zone name cannot exceed 255 characters.',
            'description.max' => 'Description cannot exceed 500 characters.',
        ];
    }
}
