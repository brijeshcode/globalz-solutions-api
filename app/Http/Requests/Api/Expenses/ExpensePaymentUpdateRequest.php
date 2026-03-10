<?php

namespace App\Http\Requests\Api\Expenses;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;

class ExpensePaymentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleHelper::canAdmin();
    }

    public function rules(): array
    {
        return [
            'account_id'     => 'required|integer|exists:accounts,id',
            'amount'         => 'required|numeric|min:0.01|max:999999999999.99',
            'date'           => 'required|date',
            'note'           => 'nullable|string|max:250',
            'order_number'   => 'nullable|string|max:100',
            'check_number'   => 'nullable|string|max:100',
            'bank_ref_number'=> 'nullable|string|max:100',
            'prefix'         => 'nullable|in:EP,EPX',
        ];
    }

    public function messages(): array
    {
        return [
            'account_id.required' => 'Account is required.',
            'account_id.exists'   => 'Selected account does not exist.',
            'amount.required'     => 'Payment amount is required.',
            'amount.min'          => 'Payment amount must be greater than 0.',
            'date.required'       => 'Payment date is required.',
            'prefix.in'           => 'Prefix must be EP or EPX.',
        ];
    }
}
