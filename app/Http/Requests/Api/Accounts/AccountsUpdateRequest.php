<?php

namespace App\Http\Requests\Api\Accounts;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountsUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return RoleHelper::isSuperAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $accountId = $this->route('account')->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('accounts', 'name')->ignore($accountId)
            ],
            'account_type_id' => 'required|integer|exists:account_types,id',
            'currency_id' => 'required|integer|exists:currencies,id',
            // Balance Information
            'opening_balance' => 'nullable|numeric|min:-999999999.9999|max:999999999.9999',
            'description' => 'nullable|string|max:65535',
            'is_active' => 'boolean',
            'is_private' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Account name is required.',
            'name.unique' => 'An account with this name already exists.',
            'name.max' => 'Account name must not exceed 255 characters.',
            'account_type_id.required' => 'Account type is required.',
            'account_type_id.exists' => 'Selected account type does not exist.',
            'currency_id.required' => 'Currency is required.',
            'currency_id.exists' => 'Selected currency does not exist.',
            'description.max' => 'Description is too long.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'account_type_id' => 'account type',
            'currency_id' => 'currency',
            'opening_balance' => 'opening balance',
        ];
    }
}
