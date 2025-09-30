<?php

namespace App\Http\Requests\Api\Customers;

use App\Helpers\ApiHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CustomerCreditDebitNotesUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'sometimes|required|date',
            'prefix' => 'sometimes|required|in:CRX,CRN,DBX,DBN',
            'type' => 'sometimes|required|in:credit,debit',
            'customer_id' => 'sometimes|required|exists:customers,id',
            'currency_id' => 'sometimes|required|exists:currencies,id',
            'currency_rate' => 'sometimes|required|numeric|min:0.0001',
            'amount' => 'sometimes|required|numeric|min:0.01',
            'amount_usd' => 'sometimes|required|numeric|min:0.01',
            'note' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Note date is required',
            'prefix.required' => 'Note prefix is required',
            'prefix.in' => 'Note prefix must be one of: CRX, CRN, DBX, DBN',
            'type.required' => 'Note type is required',
            'type.in' => 'Note type must be either credit or debit',
            'customer_id.required' => 'Customer is required',
            'customer_id.exists' => 'Selected customer does not exist',
            'currency_id.required' => 'Currency is required',
            'currency_id.exists' => 'Selected currency does not exist',
            'currency_rate.required' => 'Currency rate is required',
            'currency_rate.min' => 'Currency rate must be greater than 0',
            'amount.required' => 'Note amount is required',
            'amount.min' => 'Note amount must be greater than 0',
            'amount_usd.required' => 'Note amount in USD is required',
            'amount_usd.min' => 'Note amount in USD must be greater than 0',
            'note.max' => 'Note cannot exceed 1000 characters',
        ];
    }

    public function attributes(): array
    {
        return [
            'customer_id' => 'customer',
            'currency_id' => 'currency',
            'currency_rate' => 'currency rate',
            'amount_usd' => 'amount in USD',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $user = Auth::user();
            $isAdmin = $user && $user->isAdmin();
            $note = $this->route('customerCreditDebitNote');

            if (!$isAdmin) {
                $validator->errors()->add('authorization', 'Only administrators can update credit/debit notes');
                return;
            }

            $amount = $this->input('amount');
            $amountUsd = $this->input('amount_usd');
            $currencyRate = $this->input('currency_rate');

            if ($amount && $amountUsd && $currencyRate) {
                $expectedAmountUsd = ApiHelper::toUsd($amount, $currencyRate);
                $tolerance = 0.01;

                if (abs($expectedAmountUsd - $amountUsd) > $tolerance) {
                    $validator->errors()->add('amount_usd', 'Amount USD does not match the calculated value based on currency rate');
                }
            }

            if ($this->input('customer_id')) {
                $customer = \App\Models\Customers\Customer::find($this->input('customer_id'));
                if ($customer && !$customer->is_active) {
                    $validator->errors()->add('customer_id', 'Selected customer is inactive');
                }
            }

            $type = $this->input('type');
            $prefix = $this->input('prefix');

            if ($type && $prefix) {
                $validPrefixesForType = [
                    'credit' => ['CRX', 'CRN'],
                    'debit' => ['DBX', 'DBN']
                ];

                if (!in_array($prefix, $validPrefixesForType[$type])) {
                    $validator->errors()->add('prefix', "Prefix {$prefix} is not valid for {$type} notes");
                }
            }
        });
    }
}
