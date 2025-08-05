<?php

namespace App\Http\Requests\Api\Setups;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItemBrandsUpdateRequest extends FormRequest
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
                Rule::unique('item_brands', 'name')->ignore($this->brand)
            ],
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Brand name is required.',
            'name.unique' => 'This brand name already exists.',
            'name.max' => 'Brand name cannot exceed 255 characters.',
            'description.max' => 'Description cannot exceed 500 characters.',
        ];
    }
}