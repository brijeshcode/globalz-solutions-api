<?php

namespace App\Http\Requests\Api\Setups;

use Illuminate\Foundation\Http\FormRequest;

class WarehousesStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:warehouses,name',
            'note' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'country' => 'required|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Warehouse name is required.',
            'name.unique' => 'This warehouse name already exists.',
            'name.max' => 'Warehouse name cannot exceed 255 characters.',
            'note.max' => 'Note cannot exceed 1000 characters.',
            'address_line_1.required' => 'Address line 1 is required.',
            'address_line_1.max' => 'Address line 1 cannot exceed 255 characters.',
            'address_line_2.max' => 'Address line 2 cannot exceed 255 characters.',
            'city.required' => 'City is required.',
            'city.max' => 'City cannot exceed 100 characters.',
            'state.required' => 'State is required.',
            'state.max' => 'State cannot exceed 100 characters.',
            'postal_code.required' => 'Postal code is required.',
            'postal_code.max' => 'Postal code cannot exceed 20 characters.',
            'country.required' => 'Country is required.',
            'country.max' => 'Country cannot exceed 100 characters.',
        ];
    }
}