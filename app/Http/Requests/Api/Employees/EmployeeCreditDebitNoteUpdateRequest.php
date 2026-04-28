<?php

namespace App\Http\Requests\Api\Employees;

use App\Helpers\CurrencyHelper;
use App\Helpers\RoleHelper;
use App\Models\Employees\Employee;
use Illuminate\Foundation\Http\FormRequest;

class EmployeeCreditDebitNoteUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleHelper::canAdmin();
    }

    public function rules(): array
    {
        return [
            'date' => 'sometimes|required|date',
            'prefix' => 'sometimes|required|in:ECRX,ECRN,EDBX,EDBN',
            'type' => 'sometimes|required|in:credit,debit',
            'employee_id' => 'sometimes|required|exists:employees,id',
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
            'prefix.in' => 'Note prefix must be one of: ECRX, ECRN, EDBX, EDBN',
            'type.required' => 'Note type is required',
            'type.in' => 'Note type must be either credit or debit',
            'employee_id.required' => 'Employee is required',
            'employee_id.exists' => 'Selected employee does not exist',
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
            'employee_id' => 'employee',
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
                $employee = Employee::find($this->input('employee_id'));
                if ($employee && !$employee->is_active) {
                    $validator->errors()->add('employee_id', 'Selected employee is inactive');
                }
            }

            $type = $this->input('type');
            $prefix = $this->input('prefix');

            if ($type && $prefix) {
                $validPrefixesForType = [
                    'credit' => ['ECRX', 'ECRN'],
                    'debit' => ['EDBX', 'EDBN'],
                ];

                if (!in_array($prefix, $validPrefixesForType[$type])) {
                    $validator->errors()->add('prefix', "Prefix {$prefix} is not valid for {$type} notes");
                }
            }
        });
    }
}
