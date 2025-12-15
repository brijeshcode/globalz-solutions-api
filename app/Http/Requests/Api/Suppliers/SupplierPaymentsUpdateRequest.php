<?php

namespace App\Http\Requests\Api\Suppliers;

use App\Helpers\CurrencyHelper;
use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SupplierPaymentsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleHelper::canAdmin();
    }

    public function rules(): array
    {
        $paymentId = $this->route('supplierPayment')?->id;

        return [
            'date' => 'required|date',
            'supplier_id' => 'required|exists:suppliers,id',
            'account_id' => 'required|exists:accounts,id',
            'currency_id' => 'required|exists:currencies,id',
            'currency_rate' => 'required|numeric|min:0.0001',
            'amount' => 'required|numeric|min:0.01',
            'amount_usd' => 'required|numeric|min:0.01',
            'last_payment_amount_usd' => 'nullable|numeric|min:0',
            'supplier_order_number' => 'nullable|string|max:255',
            'check_number' => 'nullable|string|max:100',
            'bank_ref_number' => 'nullable|string|max:100',
            'note' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Payment date is required',
            'supplier_id.required' => 'Supplier is required',
            'supplier_id.exists' => 'Selected supplier does not exist',
            'account_id.required' => 'Account is required',
            'account_id.exists' => 'Selected account does not exist',
            'currency_id.required' => 'Currency is required',
            'currency_id.exists' => 'Selected currency does not exist',
            'currency_rate.required' => 'Currency rate is required',
            'currency_rate.min' => 'Currency rate must be greater than 0',
            'amount.required' => 'Payment amount is required',
            'amount.min' => 'Payment amount must be greater than 0',
            'amount_usd.required' => 'Payment amount in USD is required',
            'amount_usd.min' => 'Payment amount in USD must be greater than 0',
            'last_payment_amount_usd.min' => 'Last payment amount must be 0 or greater',
            'supplier_order_number.max' => 'Supplier order number cannot exceed 255 characters',
            'check_number.max' => 'Check number cannot exceed 100 characters',
            'bank_ref_number.max' => 'Bank reference number cannot exceed 100 characters',
        ];
    }

    public function attributes(): array
    {
        return [
            'supplier_id' => 'supplier',
            'account_id' => 'account',
            'currency_id' => 'currency',
            'currency_rate' => 'currency rate',
            'amount_usd' => 'amount in USD',
            'last_payment_amount_usd' => 'last payment amount',
            'supplier_order_number' => 'supplier order number',
            'check_number' => 'check number',
            'bank_ref_number' => 'bank reference number',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $amount = $this->input('amount');
            $amountUsd = $this->input('amount_usd');
            $currencyRate = $this->input('currency_rate');
            $currencyId = $this->input('currency_id');

            if ($amount && $amountUsd && $currencyRate && $currencyId) {
                $expectedAmountUsd = CurrencyHelper::toUsd($currencyId, $amount, $currencyRate);
                $tolerance = 0.01;

                if (abs($expectedAmountUsd - $amountUsd) > $tolerance) {
                    $validator->errors()->add('amount_usd', 'Amount USD does not match the calculated value based on currency rate');
                }
            }

            if ($this->input('supplier_id')) {
                $supplier = \App\Models\Setups\Supplier::find($this->input('supplier_id'));
                if ($supplier && !$supplier->is_active) {
                    $validator->errors()->add('supplier_id', 'Selected supplier is inactive');
                }
            }
        });
    }
}
