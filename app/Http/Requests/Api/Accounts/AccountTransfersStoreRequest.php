<?php

namespace App\Http\Requests\Api\Accounts;

use App\Helpers\RoleHelper;
use App\Models\Accounts\Account;
use Illuminate\Foundation\Http\FormRequest;

class AccountTransfersStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only super admins can create 
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
            'from_account_id' => 'required|integer|exists:accounts,id|different:to_account_id',
            'to_account_id' => 'required|integer|exists:accounts,id|different:from_account_id',
            'from_currency_id' => 'required|integer|exists:currencies,id',
            'to_currency_id' => 'required|integer|exists:currencies,id',
            'received_amount' => 'required|numeric|min:0.01',
            'sent_amount' => 'required|numeric|min:0.01',
            'currency_rate' => 'required|numeric|min:0.0001',
            'note' => 'nullable|string|max:1000',
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
            'date.required' => 'Transfer date is required',
            'from_account_id.required' => 'Source account is required',
            'from_account_id.exists' => 'Selected source account does not exist',
            'from_account_id.different' => 'Source and destination accounts must be different',
            'to_account_id.required' => 'Destination account is required',
            'to_account_id.exists' => 'Selected destination account does not exist',
            'to_account_id.different' => 'Source and destination accounts must be different',
            'from_currency_id.required' => 'Source currency is required',
            'from_currency_id.exists' => 'Selected source currency does not exist',
            'to_currency_id.required' => 'Destination currency is required',
            'to_currency_id.exists' => 'Selected destination currency does not exist',
            'received_amount.required' => 'Received amount is required',
            'received_amount.min' => 'Received amount must be greater than 0',
            'sent_amount.required' => 'Sent amount is required',
            'sent_amount.min' => 'Sent amount must be greater than 0',
            'currency_rate.required' => 'Currency rate is required',
            'currency_rate.min' => 'Currency rate must be greater than 0',
            'note.max' => 'Note cannot exceed 1000 characters',
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
            'from_account_id' => 'source account',
            'to_account_id' => 'destination account',
            'from_currency_id' => 'source currency',
            'to_currency_id' => 'destination currency',
            'received_amount' => 'received amount',
            'sent_amount' => 'sent amount',
            'currency_rate' => 'currency rate',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate that both accounts are active
            if ($this->input('from_account_id')) {
                $fromAccount = Account::find($this->input('from_account_id'));
                if ($fromAccount && !$fromAccount->is_active) {
                    $validator->errors()->add('from_account_id', 'Selected source account is inactive');
                }
            }

            if ($this->input('to_account_id')) {
                $toAccount = Account::find($this->input('to_account_id'));
                if ($toAccount && !$toAccount->is_active) {
                    $validator->errors()->add('to_account_id', 'Selected destination account is inactive');
                }
            }

            // Validate currency rate calculation
            // brijesh: todo: use currency to check this validation each currency have different calculation type

            // $sentAmount = $this->input('sent_amount');
            // $receivedAmount = $this->input('received_amount');
            // $currencyRate = $this->input('currency_rate');

            // if ($sentAmount && $receivedAmount && $currencyRate) {
            //     $expectedReceivedAmount = $sentAmount * $currencyRate;
            //     $tolerance = 0.01;

            //     if (abs($expectedReceivedAmount - $receivedAmount) > $tolerance) {
            //         $validator->errors()->add('received_amount', 'Received amount does not match the calculated value based on currency rate');
            //     }
            // }
        });
    }
}
