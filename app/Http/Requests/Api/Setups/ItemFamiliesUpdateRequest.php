<?php

namespace App\Http\Requests\Api\Setups;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItemFamiliesUpdateRequest extends FormRequest
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
                Rule::unique('item_families', 'name')->ignore($this->family)
            ],
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Family name is required.',
            'name.unique' => 'This family name already exists.',
            'name.max' => 'Family name cannot exceed 255 characters.',
            'description.max' => 'Description cannot exceed 500 characters.',
        ];
    }
}