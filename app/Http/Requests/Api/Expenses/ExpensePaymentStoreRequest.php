<?php

namespace App\Http\Requests\Api\Expenses;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;

class ExpensePaymentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleHelper::canAdmin();
    }

    public function rules(): array
    {
        return [
            'account_id' => 'required|integer|exists:accounts,id',
            'amount'     => 'required|numeric|min:0.01|max:999999999999.99',
            'date'       => 'required|date',
            'note'       => 'nullable|string|max:250',
        ];
    }

    public function messages(): array
    {
        return [
            'account_id.required' => 'Account is required.',
            'account_id.exists'   => 'Selected account does not exist.',
            'amount.required'     => 'Payment amount is required.',
            'amount.numeric'      => 'Payment amount must be a valid number.',
            'amount.min'          => 'Payment amount must be greater than 0.',
            'date.required'       => 'Payment date is required.',
            'date.date'           => 'Please provide a valid payment date.',
            'note.max'            => 'Note cannot exceed 250 characters.',
        ];
    }
}
