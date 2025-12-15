<?php

namespace App\Http\Requests\Api\Accounts;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;

class AccountAdjustUpdateRequest extends FormRequest
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
            'date' => ['required', 'date'],
            'type' => ['required', 'in:Credit,Debit'],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.required' => 'The adjustment date is required.',
            'date.date' => 'The adjustment date must be a valid date.',
            'type.required' => 'The adjustment type is required.',
            'type.in' => 'The adjustment type must be either Credit or Debit.',
            'account_id.required' => 'The account is required.',
            'account_id.exists' => 'The selected account does not exist.',
            'amount.required' => 'The adjustment amount is required.',
            'amount.numeric' => 'The adjustment amount must be a number.',
            'amount.min' => 'The adjustment amount must be greater than zero.',
            'note.max' => 'The note cannot exceed 1000 characters.',
        ];
    }

    /**
     * Get custom attribute names.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'account_id' => 'account',
            'note' => 'remarks',
        ];
    }
}
