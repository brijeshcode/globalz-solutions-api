<?php

namespace App\Http\Requests\Api\Setups\Customers;

use Illuminate\Foundation\Http\FormRequest;

class CustomerGroupsStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:customer_groups,name',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Group name is required.',
            'name.unique' => 'This group name already exists.',
            'name.max' => 'Group name cannot exceed 255 characters.',
            'description.max' => 'Description cannot exceed 500 characters.',
        ];
    }
}
