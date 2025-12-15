<?php

namespace App\Http\Requests\Api\Suppliers;

use App\Helpers\CurrencyHelper;
use App\Helpers\RoleHelper;
use App\Models\Setups\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class SupplierCreditDebitNotesStoreRequest extends FormRequest
{
    public function authorize(): bool
    { 
        return RoleHelper::canAdmin();
    }

    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'prefix' => 'required|in:SCRN,SDRN',
            'type' => 'required|in:credit,debit',
            'supplier_id' => 'required|exists:suppliers,id',
            'currency_id' => 'required|exists:currencies,id',
            'currency_rate' => 'required|numeric|min:0.0001',
            'amount' => 'required|numeric|min:0.01',
            'amount_usd' => 'required|numeric|min:0.01',
            'note' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Note date is required',
            'prefix.required' => 'Note prefix is required',
            'prefix.in' => 'Note prefix must be one of: SCRN, SDRN',
            'type.required' => 'Note type is required',
            'type.in' => 'Note type must be either credit or debit',
            'supplier_id.required' => 'Supplier is required',
            'supplier_id.exists' => 'Selected supplier does not exist',
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
            'supplier_id' => 'supplier',
            'currency_id' => 'currency',
            'currency_rate' => 'currency rate',
            'amount_usd' => 'amount in USD',
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
                $supplier = Supplier::find($this->input('supplier_id'));
                if ($supplier && !$supplier->is_active) {
                    $validator->errors()->add('supplier_id', 'Selected supplier is inactive');
                }
            }

            $type = $this->input('type');
            $prefix = $this->input('prefix');

            if ($type && $prefix) {
                $validPrefixesForType = [
                    'credit' => ['SCRN'],
                    'debit' => ['SDRN']
                ];

                if (isset($validPrefixesForType[$type]) && !in_array($prefix, $validPrefixesForType[$type])) {
                    $validator->errors()->add('prefix', "Prefix {$prefix} is not valid for {$type} notes");
                }
            }
        });
    }
}
