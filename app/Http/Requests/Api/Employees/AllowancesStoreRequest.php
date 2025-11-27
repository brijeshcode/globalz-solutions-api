<?php

namespace App\Http\Requests\Api\Employees;

use App\Helpers\CurrencyHelper;
use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;

class AllowancesStoreRequest extends FormRequest
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
            'employee_id' => 'required|exists:employees,id',
            'account_id' => 'required|exists:accounts,id',
            'currency_id' => 'required|exists:currencies,id',
            'currency_rate' => 'required|numeric|min:0.0001',
            'amount' => 'required|numeric|min:0.01',
            'amount_usd' => 'required|numeric|min:0.01',
            'note' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Allowance date is required',
            'employee_id.required' => 'Employee is required',
            'employee_id.exists' => 'Selected employee does not exist',
            'account_id.required' => 'Account is required',
            'account_id.exists' => 'Selected account does not exist',
            'currency_id.required' => 'Currency is required',
            'currency_id.exists' => 'Selected currency does not exist',
            'currency_rate.required' => 'Currency rate is required',
            'currency_rate.min' => 'Currency rate must be greater than 0',
            'amount.required' => 'Allowance amount is required',
            'amount.min' => 'Allowance amount must be greater than 0',
            'amount_usd.required' => 'Allowance amount in USD is required',
            'amount_usd.min' => 'Allowance amount in USD must be greater than 0',
        ];
    }

    public function attributes(): array
    {
        return [
            'employee_id' => 'employee',
            'account_id' => 'account',
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

            if ($this->input('employee_id')) {
                $employee = \App\Models\Employees\Employee::find($this->input('employee_id'));
                if ($employee && !$employee->is_active) {
                    $validator->errors()->add('employee_id', 'Selected employee is inactive');
                }
            }
        });
    }
}
