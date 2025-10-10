<?php

namespace App\Http\Requests\Api\Customers;

use App\Helpers\ApiHelper;
use Illuminate\Foundation\Http\FormRequest;

class CustomerPaymentOrdersStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'prefix' => 'required|in:RCT,RCX',
            'customer_id' => 'required|exists:customers,id',
            'customer_payment_term_id' => 'nullable|exists:customer_payment_terms,id',
            'currency_id' => 'required|exists:currencies,id',
            'currency_rate' => 'required|numeric|min:0.0001',
            'amount' => 'required|numeric|min:0.01',
            'amount_usd' => 'required|numeric|min:0.01',
            'credit_limit' => 'nullable|numeric|min:0',
            'last_payment_amount' => 'nullable|numeric|min:0',
            'rtc_book_number' => 'required|string|max:255|unique:customer_payments,rtc_book_number',
            'note' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Payment date is required',
            'prefix.required' => 'Payment prefix is required',
            'prefix.in' => 'Payment prefix must be either RCT or RCX',
            'customer_id.required' => 'Customer is required',
            'customer_id.exists' => 'Selected customer does not exist',
            'currency_id.required' => 'Currency is required',
            'currency_id.exists' => 'Selected currency does not exist',
            'currency_rate.required' => 'Currency rate is required',
            'currency_rate.min' => 'Currency rate must be greater than 0',
            'amount.required' => 'Payment amount is required',
            'amount.min' => 'Payment amount must be greater than 0',
            'amount_usd.required' => 'Payment amount in USD is required',
            'amount_usd.min' => 'Payment amount in USD must be greater than 0',
            'rtc_book_number.required' => 'RTC book number is required',
            'rtc_book_number.unique' => 'RTC book number must be unique',
            'credit_limit.min' => 'Credit limit must be 0 or greater',
            'last_payment_amount.min' => 'Last payment amount must be 0 or greater',
            'customer_payment_term_id.exists' => 'Selected payment term does not exist',
        ];
    }

    public function attributes(): array
    {
        return [
            'customer_id' => 'customer',
            'currency_id' => 'currency',
            'customer_payment_term_id' => 'payment term',
            'currency_rate' => 'currency rate',
            'amount_usd' => 'amount in USD',
            'credit_limit' => 'credit limit',
            'last_payment_amount' => 'last payment amount',
            'rtc_book_number' => 'RTC book number',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            /** @var \App\Models\User $user */
            

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
        });
    }
}
