<?php

namespace App\Http\Requests\Api\Accounts;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;

class IncomeTransactionsUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return RoleHelper::canAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'income_category_id' => 'required|integer|exists:income_categories,id',
            'account_id' => 'required|integer|exists:accounts,id',
            'subject' => 'nullable|string|max:200',
            'amount' => 'required|numeric|min:0|max:999999999999.99',
            'order_number' => 'nullable|string|max:100',
            'check_number' => 'nullable|string|max:100',
            'bank_ref_number' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:250',
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
            'date.required' => 'Transaction date is required.',
            'date.date' => 'Please provide a valid date.',
            'income_category_id.required' => 'Income category is required.',
            'income_category_id.exists' => 'Selected income category does not exist.',
            'account_id.required' => 'Account is required.',
            'account_id.exists' => 'Selected account does not exist.',
            'subject.max' => 'Subject cannot exceed 200 characters.',
            'amount.required' => 'Amount is required.',
            'amount.numeric' => 'Amount must be a valid number.',
            'amount.min' => 'Amount must be greater than or equal to 0.',
            'amount.max' => 'Amount exceeds the maximum allowed value.',
            'order_number.max' => 'Order number cannot exceed 100 characters.',
            'check_number.max' => 'Check number cannot exceed 100 characters.',
            'bank_ref_number.max' => 'Bank reference number cannot exceed 100 characters.',
            'note.max' => 'Note cannot exceed 250 characters.',
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
            'income_category_id' => 'income category',
            'account_id' => 'account',
            'bank_ref_number' => 'bank reference number',
        ];
    }
}
