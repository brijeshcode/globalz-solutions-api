<?php

namespace App\Http\Requests\Api\Setups\Accounts;

use Illuminate\Foundation\Http\FormRequest;

class AccountTypesStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:account_types,name',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Account type name is required.',
            'name.unique' => 'This account type name already exists.',
            'name.max' => 'Account type name cannot exceed 255 characters.',
            'description.max' => 'Description cannot exceed 500 characters.',
        ];
    }
}
